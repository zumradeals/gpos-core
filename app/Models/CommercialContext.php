<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommercialContext extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_SUSPENDED = 'SUSPENDED';

    public const ORIGIN_ZUMRA = 'ZUMRA';

    public const ORIGIN_ORGANIZATION = 'ORGANIZATION';

    public const ORIGIN_STANDALONE = 'STANDALONE';

    protected $table = 'commercial_contexts';

    protected $fillable = [
        'external_origin_type', 'external_origin_reference', 'display_name',
        'currency', 'timezone', 'status',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(CommercialContextMember::class, 'context_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'context_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'context_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'context_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'context_id');
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'context_id');
    }
}
