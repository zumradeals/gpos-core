<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registre append-only (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §11) — jamais mis à
 * jour ni supprimé applicativement.
 */
final class CashMovement extends Model
{
    use HasUuids;

    public const DIRECTION_IN = 'IN';

    public const DIRECTION_OUT = 'OUT';

    public const TYPE_OPENING_FLOAT = 'OPENING_FLOAT';

    public const TYPE_SALE_PAYMENT = 'SALE_PAYMENT';

    public const TYPE_PURCHASE_PAYMENT = 'PURCHASE_PAYMENT';

    public const TYPE_MANUAL_IN = 'MANUAL_IN';

    public const TYPE_MANUAL_OUT = 'MANUAL_OUT';

    protected $table = 'cash_movements';

    protected $fillable = [
        'context_id', 'cash_session_id', 'payment_id', 'direction', 'movement_type', 'amount_xof',
        'reason', 'source_type', 'source_reference', 'actor_core_reference', 'occurred_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['amount_xof' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
