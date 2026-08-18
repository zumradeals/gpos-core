<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotCancellableException;
use App\Domain\Identity\CurrentActor;
use App\Models\CommercialContext;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Annulation — changement d'état audité, jamais une suppression (docs/implementation/LOT-002-
 * PURCHASING-SUPPLY.md §16). Autorisée en DRAFT, ou en ORDERED tant qu'aucune réception n'existe.
 */
final class CancelPurchaseOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(PurchaseOrder $order, CurrentActor $actor): PurchaseOrder
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $order->context_id, 403);
        abort_unless($actor->can(CommercialPermission::MANAGE_PURCHASES), 403, 'Permission MANAGE_PURCHASES requise pour annuler une commande.');

        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseOrder::STATUS_CANCELLED) {
                return $locked;
            }

            if (! in_array($locked->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
                throw PurchaseOrderNotCancellableException::alreadyReceived();
            }

            if ($locked->status === PurchaseOrder::STATUS_ORDERED && $locked->receipts()->exists()) {
                throw PurchaseOrderNotCancellableException::alreadyReceived();
            }

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($locked->context_id)->firstOrFail();

            $locked->update([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancelled_by_core_reference' => $actor->identity->reference,
                'cancelled_at' => now(),
            ]);

            $this->audit->record($context, $actor->identity, 'purchase.cancelled', 'PurchaseOrder', (string) $locked->id, ['status' => $order->status], ['status' => PurchaseOrder::STATUS_CANCELLED]);

            return $locked->refresh();
        });
    }
}
