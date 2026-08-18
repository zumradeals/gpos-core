<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaleLine extends Model
{
    use HasUuids;

    protected $table = 'sale_lines';

    protected $fillable = [
        'sale_id', 'product_id', 'product_name_snapshot', 'unit_label_snapshot',
        'unit_price_xof', 'quantity', 'line_total_xof', 'track_stock_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_xof' => 'integer',
            'quantity' => 'decimal:3',
            'line_total_xof' => 'integer',
            'track_stock_snapshot' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
