<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Identity\CoreIdentityReference;
use App\Models\CashMovement;
use App\Models\CashSession;

/**
 * Écriture du registre de caisse append-only (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md
 * §11). Suppose que l'appelant a déjà verrouillé la CashSession (FOR UPDATE) dans la transaction en
 * cours et revalidé qu'elle est OPEN — ce service n'est qu'une écriture, jamais une frontière de
 * verrouillage à lui seul.
 */
final class CashLedger
{
    public function record(
        CashSession $lockedSession,
        string $movementType,
        string $direction,
        int $amountXof,
        CoreIdentityReference $actor,
        string $idempotencyKey,
        ?string $paymentId = null,
        ?string $reason = null,
        ?string $sourceType = null,
        ?string $sourceReference = null,
    ): CashMovement {
        abort_if($lockedSession->status !== CashSession::STATUS_OPEN, 409, 'Cette session de caisse est clôturée ; aucun mouvement ne peut plus y être enregistré.');
        abort_if($amountXof <= 0, 422, 'Le montant du mouvement de caisse doit être strictement positif.');

        return CashMovement::query()->create([
            'context_id' => $lockedSession->context_id,
            'cash_session_id' => $lockedSession->id,
            'payment_id' => $paymentId,
            'direction' => $direction,
            'movement_type' => $movementType,
            'amount_xof' => $amountXof,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'actor_core_reference' => $actor->reference,
            'occurred_at' => now(),
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
