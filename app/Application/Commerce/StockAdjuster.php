<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CoreIdentityReference;
use App\Models\CommercialContext;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ajustement/initialisation de stock hors vente (docs/implementation/LOT-001-APP-SHELL-COMMERCE-
 * SLICE.md §10) — action distincte et explicite, jamais un nombre modifié sans mouvement source.
 */
final class StockAdjuster
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function adjust(
        CommercialContext $context,
        CoreIdentityReference $actor,
        Product $product,
        string $direction,
        string $quantity,
        ?string $reason = null,
    ): StockMovement {
        return DB::transaction(function () use ($context, $actor, $product, $direction, $quantity, $reason): StockMovement {
            $balance = StockBalance::query()
                ->where('context_id', $context->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            $before = $balance?->quantity ?? '0.000';

            $movement = StockMovement::query()->create([
                'context_id' => $context->id,
                'product_id' => $product->id,
                'sale_line_id' => null,
                'direction' => $direction,
                'quantity' => $quantity,
                'reason' => $reason,
                'source_type' => 'MANUAL',
                'source_reference' => null,
                'actor_core_reference' => $actor->reference,
                'occurred_at' => now(),
                'idempotency_key' => (string) Str::uuid(),
            ]);

            $after = $direction === StockMovement::DIRECTION_OUT
                ? Quantity::subtract((string) $before, $quantity)
                : Quantity::add((string) $before, $quantity);

            if ($balance === null) {
                $balance = StockBalance::query()->create([
                    'context_id' => $context->id,
                    'product_id' => $product->id,
                    'quantity' => $after,
                ]);
            } else {
                $balance->update(['quantity' => $after]);
            }

            $this->audit->record(
                $context,
                $actor,
                'stock.adjusted',
                'StockBalance',
                (string) $balance->id,
                ['quantity' => (string) $before],
                ['quantity' => (string) $after],
                ['movement_id' => (string) $movement->id, 'direction' => $direction, 'reason' => $reason],
            );

            return $movement;
        });
    }
}
