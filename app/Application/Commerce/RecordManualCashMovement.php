<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\InsufficientCashBalanceException;
use App\Domain\Commerce\Exceptions\InvalidManualCashMovementException;
use App\Domain\Identity\CurrentActor;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CommercialContext;
use Illuminate\Support\Facades\DB;

/**
 * Mouvement manuel (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §15) : jamais présenté
 * comme une vente ou un achat fournisseur. Verrou : CashSession, comme toute écriture caisse.
 */
final class RecordManualCashMovement
{
    public function __construct(
        private readonly CashLedger $ledger,
        private readonly CashBalanceCalculator $calculator,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(CashSession $session, CurrentActor $actor, string $direction, int $amountXof, string $reason, string $idempotencyKey): CashMovement
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $session->context_id, 403);
        abort_unless($actor->can(CommercialPermission::OPERATE_CASH), 403, 'Permission OPERATE_CASH requise pour enregistrer un mouvement de caisse.');
        abort_unless($session->responsible_core_reference === $actor->identity->reference, 403, 'Cette session de caisse appartient à un autre acteur.');

        if ($amountXof <= 0) {
            throw InvalidManualCashMovementException::invalidAmount();
        }

        if (trim($reason) === '') {
            throw InvalidManualCashMovementException::reasonRequired();
        }

        return DB::transaction(function () use ($session, $actor, $direction, $amountXof, $reason, $idempotencyKey): CashMovement {
            // Même clé + même session/contexte -> même mouvement (idempotent). Même clé pour une
            // autre session/contexte -> échec fermé.
            $existing = CashMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_if(
                    $existing->cash_session_id !== $session->id || $existing->context_id !== $session->context_id,
                    409,
                    'Cette clé d’idempotence est déjà utilisée pour une autre session.',
                );

                return $existing;
            }

            /** @var CashSession $locked */
            $locked = CashSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($locked->context_id)->lockForUpdate()->firstOrFail();

            if ($context->status !== CommercialContext::STATUS_ACTIVE) {
                throw new CommercialContextSuspendedException;
            }

            abort_if($locked->status !== CashSession::STATUS_OPEN, 409, 'Cette session de caisse est clôturée ; aucun mouvement ne peut plus y être enregistré.');

            $movementType = $direction === CashMovement::DIRECTION_OUT ? CashMovement::TYPE_MANUAL_OUT : CashMovement::TYPE_MANUAL_IN;

            if ($direction === CashMovement::DIRECTION_OUT) {
                $expected = $this->calculator->expected($locked);

                if ($expected - $amountXof < 0) {
                    throw new InsufficientCashBalanceException($expected, $amountXof);
                }
            }

            $movement = $this->ledger->record(
                $locked,
                $movementType,
                $direction,
                $amountXof,
                $actor->identity,
                $idempotencyKey,
                reason: $reason,
            );

            $this->audit->record($context, $actor->identity, 'cash.movement_recorded', 'CashMovement', (string) $movement->id, null, [
                'movement_type' => $movementType, 'amount_xof' => $amountXof, 'reason' => $reason,
            ], requestReference: $idempotencyKey);

            return $movement;
        });
    }
}
