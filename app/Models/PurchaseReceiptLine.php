<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseReceiptLine extends Model
{
    use HasUuids;

    protected $table = 'purchase_receipt_lines';

    protected $fillable = [
        'purchase_receipt_id', 'purchase_order_line_id', 'product_id', 'product_name_snapshot',
        'unit_label_snapshot', 'quantity', 'unit_cost_xof', 'line_total_xof', 'track_stock_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost_xof' => 'integer',
            'line_total_xof' => 'integer',
            'track_stock_snapshot' => 'boolean',
        ];
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
