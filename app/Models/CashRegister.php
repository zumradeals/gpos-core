<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CashRegister extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_SUSPENDED = 'SUSPENDED';

    protected $table = 'cash_registers';

    protected $fillable = ['context_id', 'name', 'code', 'status', 'created_by_core_reference'];

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'cash_register_id');
    }
}
