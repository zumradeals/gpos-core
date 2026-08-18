<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Sale extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'sales';

    protected $fillable = [
        'context_id', 'reference', 'status', 'subtotal_xof', 'discount_xof', 'total_xof',
        'currency', 'created_by_core_reference', 'confirmed_by_core_reference', 'confirmed_at',
        'client_reference', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_xof' => 'integer',
            'discount_xof' => 'integer',
            'total_xof' => 'integer',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class, 'sale_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'sale_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(CommercialDocument::class, 'sale_id');
    }
}
