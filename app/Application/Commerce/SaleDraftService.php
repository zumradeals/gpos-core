<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CoreIdentityReference;
use App\Models\CommercialContext;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Support\Facades\DB;

/**
 * Construction d'une vente DRAFT (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §11.2 :
 * « vente DRAFT modifiable »). Les lignes recalculent toujours leurs totaux côté serveur — jamais
 * un total envoyé par le client, jamais un float dans le calcul monétaire (§2).
 *
 * Chaque mutation reverrouille et revalide la vente elle-même : statut DRAFT, et — pour l'ajout
 * d'un produit — appartenance au même contexte commercial. Le service ne fait jamais confiance à
 * l'appelant (Livewire ou autre) pour avoir déjà vérifié ces invariants ; le verrou de ligne sur
 * la vente garantit aussi qu'une confirmation concurrente (ConfirmCashSale, qui verrouille la
 * même ligne en premier) et une mutation de brouillon se sérialisent proprement.
 */
final class SaleDraftService
{
    public function findOrCreateDraft(CommercialContext $context, CoreIdentityReference $actor): Sale
    {
        $draft = Sale::query()
            ->where('context_id', $context->id)
            ->where('status', Sale::STATUS_DRAFT)
            ->where('created_by_core_reference', $actor->reference)
            ->latest('created_at')
            ->first();

        if ($draft !== null) {
            return $draft;
        }

        return Sale::query()->create([
            'context_id' => $context->id,
            'status' => Sale::STATUS_DRAFT,
            'currency' => $context->currency,
            'created_by_core_reference' => $actor->reference,
        ]);
    }

    public function addOrIncrementLine(Sale $sale, Product $product, string $quantity = '1'): SaleLine
    {
        return DB::transaction(function () use ($sale, $product, $quantity): SaleLine {
            $lockedSale = $this->lockDraftSale($sale);

            abort_if($product->context_id !== $lockedSale->context_id, 403, 'Ce produit n’appartient pas à ce contexte commercial.');

            $line = SaleLine::query()
                ->where('sale_id', $lockedSale->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($line !== null) {
                return $this->applyQuantity($lockedSale, $line, Quantity::add((string) $line->quantity, $quantity));
            }

            $unitPrice = $product->sale_price_xof;
            $line = SaleLine::query()->create([
                'sale_id' => $lockedSale->id,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'unit_label_snapshot' => $product->unit_label,
                'unit_price_xof' => $unitPrice,
                'quantity' => $quantity,
                'line_total_xof' => Quantity::moneyForUnitPrice($unitPrice, $quantity),
                'track_stock_snapshot' => $product->track_stock,
            ]);

            $this->recomputeTotals($lockedSale);

            return $line->refresh();
        });
    }

    public function setQuantity(Sale $sale, SaleLine $line, string $quantity): SaleLine
    {
        return DB::transaction(function () use ($sale, $line, $quantity): SaleLine {
            $lockedSale = $this->lockDraftSale($sale);

            abort_if($line->sale_id !== $lockedSale->id, 403, 'Cette ligne n’appartient pas à cette vente.');

            return $this->applyQuantity($lockedSale, $line, $quantity);
        });
    }

    public function removeLine(Sale $sale, SaleLine $line): void
    {
        DB::transaction(function () use ($sale, $line): void {
            $lockedSale = $this->lockDraftSale($sale);

            abort_if($line->sale_id !== $lockedSale->id, 403, 'Cette ligne n’appartient pas à cette vente.');

            $line->delete();
            $this->recomputeTotals($lockedSale);
        });
    }

    public function recomputeTotals(Sale $sale): Sale
    {
        $subtotal = (int) $sale->lines()->sum('line_total_xof');
        $sale->update([
            'subtotal_xof' => $subtotal,
            'total_xof' => max(0, $subtotal - $sale->discount_xof),
        ]);

        return $sale->refresh();
    }

    /**
     * Suppose la vente déjà verrouillée et validée DRAFT par l'appelant (lockDraftSale). Verrouille
     * en plus la ligne elle-même avant de la modifier ou de la supprimer.
     */
    private function applyQuantity(Sale $lockedSale, SaleLine $line, string $quantity): SaleLine
    {
        $line = SaleLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();

        if (Quantity::compare($quantity, '0') <= 0) {
            $line->delete();
            $this->recomputeTotals($lockedSale);

            return $line;
        }

        $line->update([
            'quantity' => $quantity,
            'line_total_xof' => Quantity::moneyForUnitPrice($line->unit_price_xof, $quantity),
        ]);

        $this->recomputeTotals($lockedSale);

        return $line->refresh();
    }

    /**
     * Verrou de ligne sur la vente + statut DRAFT exigé. Le même verrou est pris en premier par
     * ConfirmCashSale::handle() : les deux opérations se sérialisent, jamais d'entrelacement.
     */
    private function lockDraftSale(Sale $sale): Sale
    {
        $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

        abort_if($locked->status !== Sale::STATUS_DRAFT, 409, 'Cette vente est déjà confirmée ou annulée ; elle ne peut plus être modifiée.');

        return $locked;
    }
}
