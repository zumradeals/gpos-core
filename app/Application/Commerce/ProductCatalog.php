<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Identity\CoreIdentityReference;
use App\Models\CommercialContext;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue minimal (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §9). Un produit
 * suivi en stock démarre avec un solde de zéro explicite, jamais un solde manquant/implicite.
 */
final class ProductCatalog
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(CommercialContext $context, CoreIdentityReference $actor, array $data): Product
    {
        return DB::transaction(function () use ($context, $actor, $data): Product {
            $product = Product::query()->create([
                'context_id' => $context->id,
                'name' => $data['name'],
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'kind' => $data['kind'] ?? Product::KIND_PRODUCT,
                'sale_price_xof' => $data['sale_price_xof'],
                'track_stock' => $data['kind'] === Product::KIND_SERVICE ? false : ($data['track_stock'] ?? true),
                'active' => true,
                'unit_label' => $data['unit_label'] ?? 'unité',
                'reorder_threshold' => ($data['kind'] ?? Product::KIND_PRODUCT) === Product::KIND_SERVICE ? null : ($data['reorder_threshold'] ?? null),
            ]);

            if ($product->track_stock) {
                StockBalance::query()->create([
                    'context_id' => $context->id,
                    'product_id' => $product->id,
                    'quantity' => 0,
                ]);
            }

            $this->audit->record(
                $context,
                $actor,
                'product.created',
                'Product',
                (string) $product->id,
                null,
                $product->only(['name', 'sku', 'kind', 'sale_price_xof', 'track_stock']),
            );

            return $product;
        });
    }
}
