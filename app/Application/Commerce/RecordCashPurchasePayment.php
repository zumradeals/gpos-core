<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\InsufficientCashBalanceException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotPayableException;
use App\Domain\Identity\CurrentActor;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CommercialContext;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Paiement comptant fournisseur, volontairement borné (docs/implementation/LOT-002-PURCHASING-
 * SUPPLY.md §17.2) : commande RECEIVED, paiement unique, montant exact, CASH uniquement — aucune
 * dette ni échéancier. Réutilise le domaine Payment de LOT-001 plutôt qu'un second moteur
 * financier.
 *
 * Modification bornée LOT-003 (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §13.2) : dans
 * la même transaction, exige désormais une session de caisse ouverte pour l'acteur, avec un
 * attendu disponible suffisant, et produit exactement un CashMovement PURCHASE_PAYMENT lié au
 * Payment — sinon rollback complet, aucun Payment n'est créé.
 */
final class RecordCashPurchasePayment
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CashSessionResolver $cashSessionResolver,
        private readonly CashBalanceCalculator $cashBalanceCalculator,
        private readonly CashLedger $cashLedger,
    ) {}

    public function handle(PurchaseOrder $order, CurrentActor $actor, string $idempotencyKey): Payment
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $order->context_id, 403);
        abort_unless($actor->can(CommercialPermission::PAY_PURCHASES), 403, 'Permission PAY_PURCHASES requise pour enregistrer un paiement.');

        return DB::transaction(function () use ($order, $actor, $idempotencyKey): Payment {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = Payment::query()->where('purchase_order_id', $locked->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($locked->context_id)->lockForUpdate()->firstOrFail();

            if ($context->status !== CommercialContext::STATUS_ACTIVE) {
                throw new CommercialContextSuspendedException;
            }

            abort_unless($locked->status === PurchaseOrder::STATUS_RECEIVED, 409, PurchaseOrderNotPayableException::notFullyReceived()->getMessage());

            if ($locked->total_xof === 0) {
                throw PurchaseOrderNotPayableException::nothingToPay();
            }

            abort_unless($actor->can(CommercialPermission::OPERATE_CASH), 403, 'Permission OPERATE_CASH requise pour payer en espèces.');

            $openSession = $this->cashSessionResolver->requireOpenSessionForActor($context, $actor->identity);

            /** @var CashSession $lockedSession */
            $lockedSession = CashSession::query()->whereKey($openSession->id)->lockForUpdate()->firstOrFail();

            abort_if($lockedSession->status !== CashSession::STATUS_OPEN, 409, 'Cette session de caisse est clôturée ; aucun paiement ne peut plus y être enregistré.');

            $expected = $this->cashBalanceCalculator->expected($lockedSession);

            if ($expected - $locked->total_xof < 0) {
                throw new InsufficientCashBalanceException($expected, $locked->total_xof);
            }

            $payment = Payment::query()->create([
                'context_id' => $context->id,
                'purchase_order_id' => $locked->id,
                'method' => Payment::METHOD_CASH,
                'amount_xof' => $locked->total_xof,
                'status' => Payment::STATUS_CONFIRMED,
                'actor_core_reference' => $actor->identity->reference,
                'paid_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->cashLedger->record(
                $lockedSession,
                CashMovement::TYPE_PURCHASE_PAYMENT,
                CashMovement::DIRECTION_OUT,
                $payment->amount_xof,
                $actor->identity,
                'PURCHASE_PAYMENT_CASH_MOVEMENT:'.$payment->id,
                paymentId: (string) $payment->id,
                sourceType: 'PURCHASE_ORDER',
                sourceReference: (string) $locked->id,
            );

            $this->audit->record($context, $actor->identity, 'purchase.payment_confirmed', 'Payment', (string) $payment->id, null, [
                'purchase_order_id' => (string) $locked->id, 'amount_xof' => $payment->amount_xof,
            ], requestReference: $idempotencyKey);

            $this->audit->record($context, $actor->identity, 'cash.payment_linked', 'Payment', (string) $payment->id, null, [
                'cash_session_id' => (string) $lockedSession->id, 'amount_xof' => $payment->amount_xof,
            ], requestReference: $idempotencyKey);

            return $payment;
        });
    }
}
