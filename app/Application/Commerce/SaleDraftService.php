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
 * un total envoyé par le client.
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
            $line = SaleLine::query()
                ->where('sale_id', $sale->id)
                ->where('product_id', $product->id)
                ->first();

            if ($line !== null) {
                $line = $this->setQuantity($sale, $line, Quantity::add((string) $line->quantity, $quantity));
            } else {
                $unitPrice = $product->sale_price_xof;
                $line = SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_label_snapshot' => $product->unit_label,
                    'unit_price_xof' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total_xof' => (int) round($unitPrice * (float) $quantity),
                    'track_stock_snapshot' => $product->track_stock,
                ]);
            }

            $this->recomputeTotals($sale);

            return $line->refresh();
        });
    }

    public function setQuantity(Sale $sale, SaleLine $line, string $quantity): SaleLine
    {
        if (Quantity::compare($quantity, '0') <= 0) {
            $line->delete();
            $this->recomputeTotals($sale);

            return $line;
        }

        $line->update([
            'quantity' => $quantity,
            'line_total_xof' => (int) round($line->unit_price_xof * (float) $quantity),
        ]);

        $this->recomputeTotals($sale);

        return $line->refresh();
    }

    public function removeLine(Sale $sale, SaleLine $line): void
    {
        $line->delete();
        $this->recomputeTotals($sale);
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
}
