<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CashSession extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'OPEN';

    public const STATUS_CLOSED = 'CLOSED';

    public const STATUS_CLOSED_WITH_VARIANCE = 'CLOSED_WITH_VARIANCE';

    protected $table = 'cash_sessions';

    protected $fillable = [
        'context_id', 'cash_register_id', 'reference', 'status', 'responsible_core_reference',
        'opening_amount_xof', 'opened_at', 'opened_by_core_reference', 'opening_idempotency_key',
        'counted_amount_xof', 'expected_amount_xof_snapshot', 'variance_xof', 'variance_reason',
        'closed_at', 'closed_by_core_reference', 'closure_idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount_xof' => 'integer',
            'opened_at' => 'immutable_datetime',
            'counted_amount_xof' => 'integer',
            'expected_amount_xof_snapshot' => 'integer',
            'variance_xof' => 'integer',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(CommercialDocument::class, 'cash_session_id');
    }
}
