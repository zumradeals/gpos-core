<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Models\CommercialContext;
use App\Models\CommercialContextSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Compteur par contexte sous verrou de ligne — deux confirmations concurrentes dans le même
 * contexte n'obtiennent jamais le même numéro (docs/architecture/SATELLITE-CONTRACT.md §12).
 */
final class SequenceGenerator
{
    public function next(CommercialContext $context, string $type): int
    {
        try {
            return $this->increment($context, $type);
        } catch (UniqueConstraintViolationException) {
            // Deux premières demandes concurrentes pour ce (contexte, type) : l'une des deux a
            // créé la ligne de compteur en premier, l'autre retente simplement l'incrément.
            return $this->increment($context, $type);
        }
    }

    private function increment(CommercialContext $context, string $type): int
    {
        return DB::transaction(function () use ($context, $type): int {
            $row = CommercialContextSequence::query()
                ->where('context_id', $context->id)
                ->where('sequence_type', $type)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $row = CommercialContextSequence::query()->create([
                    'context_id' => $context->id,
                    'sequence_type' => $type,
                    'last_value' => 0,
                ]);
            }

            $next = $row->last_value + 1;
            $row->update(['last_value' => $next]);

            return $next;
        });
    }
}
