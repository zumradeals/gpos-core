<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseOrderLine extends Model
{
    use HasUuids;

    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id', 'product_id', 'product_name_snapshot', 'unit_label_snapshot',
        'unit_cost_xof', 'ordered_quantity', 'received_quantity', 'line_total_xof',
        'track_stock_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost_xof' => 'integer',
            'ordered_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'line_total_xof' => 'integer',
            'track_stock_snapshot' => 'boolean',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
