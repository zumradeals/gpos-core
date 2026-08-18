<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    use HasUuids;

    public const METHOD_CASH = 'CASH';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    protected $table = 'payments';

    protected $fillable = [
        'context_id', 'sale_id', 'method', 'amount_xof', 'status',
        'actor_core_reference', 'paid_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['amount_xof' => 'integer', 'paid_at' => 'immutable_datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
