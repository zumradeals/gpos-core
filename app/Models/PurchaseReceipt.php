<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class PurchaseReceipt extends Model
{
    use HasUuids;

    protected $table = 'purchase_receipts';

    protected $fillable = [
        'context_id', 'purchase_order_id', 'reference', 'received_by_core_reference',
        'received_at', 'note', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime'];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class, 'purchase_receipt_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(CommercialDocument::class, 'purchase_receipt_id');
    }
}
