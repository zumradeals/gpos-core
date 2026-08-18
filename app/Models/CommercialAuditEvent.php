<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only : jamais mis à jour ni supprimé applicativement (docs/architecture/
 * SATELLITE-CONTRACT.md §9). Pas de $table->timestamps() habituel — seul created_at existe et
 * n'est jamais modifié.
 */
final class CommercialAuditEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'commercial_audit_events';

    protected $fillable = [
        'context_id', 'actor_core_reference', 'event_type', 'aggregate_type',
        'aggregate_reference', 'before_state', 'after_state', 'metadata',
        'occurred_at', 'request_reference',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
