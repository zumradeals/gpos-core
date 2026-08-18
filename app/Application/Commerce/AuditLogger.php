<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Identity\CoreIdentityReference;
use App\Models\CommercialAuditEvent;
use App\Models\CommercialContext;

/**
 * Écriture du journal d'audit append-only (docs/architecture/SATELLITE-CONTRACT.md §9) : qui,
 * quoi, quand, dans quel contexte, depuis/vers quel état. Ne jamais stocker de secret dans
 * metadata.
 */
final class AuditLogger
{
    public function record(
        CommercialContext $context,
        CoreIdentityReference $actor,
        string $eventType,
        string $aggregateType,
        string $aggregateReference,
        ?array $beforeState = null,
        ?array $afterState = null,
        array $metadata = [],
        ?string $requestReference = null,
    ): CommercialAuditEvent {
        return CommercialAuditEvent::query()->create([
            'context_id' => $context->id,
            'actor_core_reference' => $actor->reference,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_reference' => $aggregateReference,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'metadata' => $metadata,
            'occurred_at' => now(),
            'request_reference' => $requestReference,
        ]);
    }
}
