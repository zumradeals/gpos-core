<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CashSessionNotCloseableException;
use App\Domain\Commerce\Exceptions\CashVarianceReasonRequiredException;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Identity\CurrentActor;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CommercialContext;
use App\Models\CommercialContextSequence;
use App\Models\CommercialDocument;
use Illuminate\Support\Facades\DB;

/**
 * Clôture de caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §17). Verrou de session
 * en premier, comme toute écriture caisse — une clôture gagnante empêche tout paiement concurrent
 * d'écrire ensuite dans la session (§16).
 */
final class CloseCashSession
{
    public function __construct(
        private readonly SequenceGenerator $sequences,
        private readonly CashBalanceCalculator $calculator,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(CashSession $session, CurrentActor $actor, int $countedAmountXof, ?string $varianceReason, string $idempotencyKey): CloseCashSessionResult
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $session->context_id, 403);
        abort_unless($actor->can(CommercialPermission::CLOSE_CASH), 403, 'Permission CLOSE_CASH requise pour clôturer une caisse.');

        $isOwnSession = $actor->identity->reference === $session->responsible_core_reference;
        if (! $isOwnSession) {
            abort_unless($actor->can(CommercialPermission::MANAGE_CASH), 403, 'Seul un gestionnaire de caisse peut clôturer la session d’un autre acteur.');
        }

        if ($countedAmountXof < 0) {
            throw CashSessionNotCloseableException::invalidCountedAmount();
        }

        return DB::transaction(function () use ($session, $actor, $countedAmountXof, $varianceReason, $idempotencyKey): CloseCashSessionResult {
            // Même clé + même session déjà clôturée -> même preuve (idempotent). Même clé pour
            // une autre session/contexte -> échec fermé, jamais une preuve étrangère.
            $existing = CashSession::query()->where('closure_idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_if(
                    $existing->id !== $session->id || $existing->context_id !== $session->context_id,
                    409,
                    'Cette clé d’idempotence est déjà utilisée pour une autre session de caisse.',
                );

                return new CloseCashSessionResult($existing, $existing->document()->firstOrFail());
            }

            /** @var CashSession $locked */
            $locked = CashSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            // Cette clé ne correspond à aucune session déjà clôturée (vérifié ci-dessus) : si la
            // session est déjà fermée, c'est un conflit explicite avec une AUTRE clé.
            if ($locked->status !== CashSession::STATUS_OPEN) {
                throw CashSessionNotCloseableException::alreadyClosed();
            }

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($locked->context_id)->lockForUpdate()->firstOrFail();

            if ($context->status !== CommercialContext::STATUS_ACTIVE) {
                throw new CommercialContextSuspendedException;
            }

            /** @var CashRegister $register */
            $register = CashRegister::query()->whereKey($locked->cash_register_id)->firstOrFail();

            $expected = $this->calculator->expected($locked);
            $variance = $countedAmountXof - $expected;

            if ($variance !== 0) {
                if ($varianceReason === null || trim($varianceReason) === '') {
                    throw new CashVarianceReasonRequiredException($variance);
                }
            } else {
                $varianceReason = null;
            }

            $movements = $locked->movements()->orderBy('occurred_at')->get();
            $totalIn = (int) $movements->where('direction', CashMovement::DIRECTION_IN)->sum('amount_xof');
            $totalOut = (int) $movements->where('direction', CashMovement::DIRECTION_OUT)->sum('amount_xof');

            $status = $variance === 0 ? CashSession::STATUS_CLOSED : CashSession::STATUS_CLOSED_WITH_VARIANCE;

            $locked->update([
                'status' => $status,
                'counted_amount_xof' => $countedAmountXof,
                'expected_amount_xof_snapshot' => $expected,
                'variance_xof' => $variance,
                'variance_reason' => $varianceReason,
                'closed_at' => now(),
                'closed_by_core_reference' => $actor->identity->reference,
                'closure_idempotency_key' => $idempotencyKey,
            ]);

            $document = CommercialDocument::query()->create([
                'context_id' => $context->id,
                'cash_session_id' => $locked->id,
                'document_type' => CommercialDocument::TYPE_CASH_CLOSURE,
                'number' => 'CLT-'.$this->sequences->next($context, CommercialContextSequence::CASH_CLOSURE),
                'snapshot' => $this->buildClosureSnapshot($locked, $register, $context, $totalIn, $totalOut, $expected, $countedAmountXof, $variance, $varianceReason, $actor),
                'issued_at' => now(),
                'issued_by_core_reference' => $actor->identity->reference,
            ]);

            $this->audit->record($context, $actor->identity, 'cash.session_closed', 'CashSession', (string) $locked->id, null, [
                'reference' => $locked->reference, 'status' => $status,
            ], requestReference: $idempotencyKey);

            if ($variance !== 0) {
                $this->audit->record($context, $actor->identity, 'cash.variance_recorded', 'CashSession', (string) $locked->id, null, [
                    'variance_xof' => $variance, 'reason' => $varianceReason,
                ], requestReference: $idempotencyKey);
            }

            $this->audit->record($context, $actor->identity, 'document.issued', 'CommercialDocument', (string) $document->id, null, [
                'number' => $document->number,
            ], requestReference: $idempotencyKey);

            return new CloseCashSessionResult($locked->refresh(), $document);
        });
    }

    private function buildClosureSnapshot(
        CashSession $session,
        CashRegister $register,
        CommercialContext $context,
        int $totalIn,
        int $totalOut,
        int $expected,
        int $counted,
        int $variance,
        ?string $varianceReason,
        CurrentActor $actor,
    ): array {
        return [
            'context_display_name' => $context->display_name,
            'currency' => $context->currency,
            'register_name' => $register->name,
            'register_code' => $register->code,
            'session_reference' => $session->reference,
            'responsible_core_reference' => $session->responsible_core_reference,
            'opened_at' => $session->opened_at->toIso8601String(),
            'closed_at' => now()->toIso8601String(),
            'opening_amount_xof' => $session->opening_amount_xof,
            'total_in_xof' => $totalIn,
            'total_out_xof' => $totalOut,
            'expected_amount_xof' => $expected,
            'counted_amount_xof' => $counted,
            'variance_xof' => $variance,
            'variance_reason' => $varianceReason,
            'closed_by_core_reference' => $actor->identity->reference,
        ];
    }
}
