<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rendu figé d'une vérité métier versionnée (docs/architecture/SATELLITE-CONTRACT.md §13) —
 * modifier un produit ensuite ne réécrit jamais silencieusement ce snapshot.
 */
final class CommercialDocument extends Model
{
    use HasUuids;

    public const TYPE_RECEIPT = 'RECEIPT';

    public const TYPE_PURCHASE_ORDER = 'PURCHASE_ORDER';

    public const TYPE_GOODS_RECEIPT = 'GOODS_RECEIPT';

    protected $table = 'commercial_documents';

    protected $fillable = [
        'context_id', 'sale_id', 'purchase_order_id', 'purchase_receipt_id', 'document_type',
        'number', 'snapshot', 'issued_at', 'issued_by_core_reference',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'issued_at' => 'immutable_datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }
}
