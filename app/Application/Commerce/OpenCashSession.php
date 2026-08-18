<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CashSessionNotOpenableException;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Identity\CurrentActor;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CommercialContext;
use App\Models\CommercialContextSequence;
use Illuminate\Support\Facades\DB;

/**
 * Ouverture de session de caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §10). Ordre
 * de verrou : CashRegister d'abord, comme partout ailleurs dans LOT-003, avant toute décision.
 */
final class OpenCashSession
{
    public function __construct(
        private readonly SequenceGenerator $sequences,
        private readonly CashLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(CashRegister $register, CurrentActor $actor, int $openingAmountXof, string $idempotencyKey): CashSession
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $register->context_id, 403);
        abort_unless($actor->can(CommercialPermission::OPERATE_CASH), 403, 'Permission OPERATE_CASH requise pour ouvrir une caisse.');

        if ($openingAmountXof < 0) {
            throw CashSessionNotOpenableException::invalidOpeningAmount();
        }

        return DB::transaction(function () use ($register, $actor, $openingAmountXof, $idempotencyKey): CashSession {
            // Même clé + même caisse/contexte -> même session (idempotent). Même clé pour une
            // autre caisse/contexte -> échec fermé, jamais une session étrangère.
            $existing = CashSession::query()->where('opening_idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_if(
                    $existing->cash_register_id !== $register->id || $existing->context_id !== $register->context_id,
                    409,
                    'Cette clé d’idempotence est déjà utilisée pour une autre caisse.',
                );

                return $existing;
            }

            /** @var CashRegister $lockedRegister */
            $lockedRegister = CashRegister::query()->whereKey($register->id)->lockForUpdate()->firstOrFail();

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($lockedRegister->context_id)->lockForUpdate()->firstOrFail();

            if ($context->status !== CommercialContext::STATUS_ACTIVE) {
                throw new CommercialContextSuspendedException;
            }

            if ($lockedRegister->status !== CashRegister::STATUS_ACTIVE) {
                throw CashSessionNotOpenableException::registerSuspended();
            }

            $registerAlreadyOpen = CashSession::query()
                ->where('cash_register_id', $lockedRegister->id)
                ->where('status', CashSession::STATUS_OPEN)
                ->exists();

            if ($registerAlreadyOpen) {
                throw CashSessionNotOpenableException::registerAlreadyOpen();
            }

            $actorAlreadyOpen = CashSession::query()
                ->where('context_id', $context->id)
                ->where('responsible_core_reference', $actor->identity->reference)
                ->where('status', CashSession::STATUS_OPEN)
                ->exists();

            if ($actorAlreadyOpen) {
                throw CashSessionNotOpenableException::actorAlreadyHasOpenSession();
            }

            $session = CashSession::query()->create([
                'context_id' => $context->id,
                'cash_register_id' => $lockedRegister->id,
                'reference' => 'CAI-'.$this->sequences->next($context, CommercialContextSequence::CASH_SESSION),
                'status' => CashSession::STATUS_OPEN,
                'responsible_core_reference' => $actor->identity->reference,
                'opening_amount_xof' => $openingAmountXof,
                'opened_at' => now(),
                'opened_by_core_reference' => $actor->identity->reference,
                'opening_idempotency_key' => $idempotencyKey,
            ]);

            // Fonds initial > 0 : exactement un OPENING_FLOAT IN. Un fonds à 0 est valide et ne
            // crée volontairement aucun mouvement zéro (§10.2) — la vérité reste
            // opening_amount_xof sur la session elle-même.
            if ($openingAmountXof > 0) {
                $this->ledger->record(
                    $session,
                    CashMovement::TYPE_OPENING_FLOAT,
                    CashMovement::DIRECTION_IN,
                    $openingAmountXof,
                    $actor->identity,
                    'OPENING_FLOAT:'.$session->id,
                    reason: 'Fonds de départ',
                );
            }

            $this->audit->record($context, $actor->identity, 'cash.session_opened', 'CashSession', (string) $session->id, null, [
                'reference' => $session->reference, 'opening_amount_xof' => $openingAmountXof,
            ], requestReference: $idempotencyKey);

            return $session->refresh();
        });
    }
}
