<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CoreIdentityReference;
use App\Models\CommercialContext;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Construction d'une commande d'achat DRAFT (docs/implementation/LOT-002-PURCHASING-SUPPLY.md
 * §11). Chaque mutation reverrouille et revalide la commande elle-même : statut DRAFT, et
 * appartenance produit/contexte — le service ne fait jamais confiance à l'appelant (Livewire ou
 * autre) pour avoir déjà vérifié ces invariants. Totaux toujours recalculés côté serveur, jamais
 * acceptés depuis le navigateur.
 */
final class PurchaseOrderDraftService
{
    public function createDraft(CommercialContext $context, CoreIdentityReference $actor, Supplier $supplier): PurchaseOrder
    {
        abort_if($supplier->context_id !== $context->id, 403, 'Ce fournisseur n’appartient pas à ce contexte commercial.');

        return PurchaseOrder::query()->create([
            'context_id' => $context->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'currency' => $context->currency,
            'created_by_core_reference' => $actor->reference,
        ]);
    }

    public function addOrUpdateLine(PurchaseOrder $order, Product $product, string $quantity, int $unitCostXof): PurchaseOrderLine
    {
        return DB::transaction(function () use ($order, $product, $quantity, $unitCostXof): PurchaseOrderLine {
            $lockedOrder = $this->lockDraftOrder($order);

            abort_if($product->context_id !== $lockedOrder->context_id, 403, 'Ce produit n’appartient pas à ce contexte commercial.');
            abort_if(Quantity::compare($quantity, '0') <= 0, 422, 'La quantité commandée doit être positive.');
            abort_if($unitCostXof < 0, 422, 'Le coût unitaire ne peut pas être négatif.');

            $line = PurchaseOrderLine::query()
                ->where('purchase_order_id', $lockedOrder->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $lineTotal = Quantity::moneyForUnitPrice($unitCostXof, $quantity);

            if ($line !== null) {
                $line->update([
                    'unit_cost_xof' => $unitCostXof,
                    'ordered_quantity' => $quantity,
                    'line_total_xof' => $lineTotal,
                ]);
            } else {
                $line = PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $lockedOrder->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_label_snapshot' => $product->unit_label,
                    'unit_cost_xof' => $unitCostXof,
                    'ordered_quantity' => $quantity,
                    'received_quantity' => 0,
                    'line_total_xof' => $lineTotal,
                    'track_stock_snapshot' => $product->track_stock,
                ]);
            }

            $this->recomputeTotals($lockedOrder);

            return $line->refresh();
        });
    }

    public function removeLine(PurchaseOrder $order, PurchaseOrderLine $line): void
    {
        DB::transaction(function () use ($order, $line): void {
            $lockedOrder = $this->lockDraftOrder($order);

            abort_if($line->purchase_order_id !== $lockedOrder->id, 403, 'Cette ligne n’appartient pas à cette commande.');

            $line->delete();
            $this->recomputeTotals($lockedOrder);
        });
    }

    public function recomputeTotals(PurchaseOrder $order): PurchaseOrder
    {
        $subtotal = (int) $order->lines()->sum('line_total_xof');
        $order->update([
            'subtotal_xof' => $subtotal,
            'total_xof' => $subtotal,
        ]);

        return $order->refresh();
    }

    /**
     * Verrou de ligne sur la commande + statut DRAFT exigé. Même verrou pris en premier par
     * ConfirmPurchaseOrder::handle() : les deux opérations se sérialisent, jamais d'entrelacement.
     */
    private function lockDraftOrder(PurchaseOrder $order): PurchaseOrder
    {
        $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

        abort_if($locked->status !== PurchaseOrder::STATUS_DRAFT, 409, 'Cette commande est déjà confirmée ou annulée ; elle ne peut plus être modifiée.');

        return $locked;
    }
}
