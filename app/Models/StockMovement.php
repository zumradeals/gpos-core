<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockMovement extends Model
{
    use HasUuids;

    public const DIRECTION_IN = 'IN';

    public const DIRECTION_OUT = 'OUT';

    public const DIRECTION_ADJUSTMENT = 'ADJUSTMENT';

    protected $table = 'stock_movements';

    protected $fillable = [
        'context_id', 'product_id', 'sale_line_id', 'purchase_receipt_line_id', 'direction',
        'quantity', 'reason', 'source_type', 'source_reference', 'actor_core_reference',
        'occurred_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'occurred_at' => 'immutable_datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }

    public function purchaseReceiptLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptLine::class, 'purchase_receipt_line_id');
    }
}
