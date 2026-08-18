<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Product extends Model
{
    use HasUuids;

    public const KIND_PRODUCT = 'PRODUCT';

    public const KIND_SERVICE = 'SERVICE';

    protected $table = 'products';

    protected $fillable = [
        'context_id', 'name', 'sku', 'barcode', 'kind',
        'sale_price_xof', 'track_stock', 'active', 'unit_label', 'reorder_threshold',
    ];

    protected function casts(): array
    {
        return [
            'sale_price_xof' => 'integer',
            'track_stock' => 'boolean',
            'active' => 'boolean',
            'reorder_threshold' => 'decimal:3',
        ];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }

    public function stockBalance(): HasOne
    {
        return $this->hasOne(StockBalance::class, 'product_id');
    }
}
