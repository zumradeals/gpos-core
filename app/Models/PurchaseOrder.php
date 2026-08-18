<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class PurchaseOrder extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_ORDERED = 'ORDERED';

    public const STATUS_PARTIALLY_RECEIVED = 'PARTIALLY_RECEIVED';

    public const STATUS_RECEIVED = 'RECEIVED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'purchase_orders';

    protected $fillable = [
        'context_id', 'supplier_id', 'reference', 'status', 'currency',
        'supplier_display_name_snapshot', 'subtotal_xof', 'total_xof', 'expected_on', 'note',
        'created_by_core_reference', 'ordered_by_core_reference', 'ordered_at',
        'cancelled_by_core_reference', 'cancelled_at', 'confirmation_idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_xof' => 'integer',
            'total_xof' => 'integer',
            'expected_on' => 'date',
            'ordered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class, 'purchase_order_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'purchase_order_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(CommercialDocument::class, 'purchase_order_id');
    }
}
