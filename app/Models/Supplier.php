<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fournisseur — relation commerciale locale scoppée au contexte (docs/implementation/LOT-002-
 * PURCHASING-SUPPLY.md §7), jamais une identité GAMAD/DG Afrique. external_origin_* préparent une
 * intégration future sans prétendre qu'elle existe déjà.
 */
final class Supplier extends Model
{
    use HasUuids;

    protected $table = 'suppliers';

    protected $fillable = [
        'context_id', 'display_name', 'contact_name', 'phone', 'email', 'notes',
        'external_origin_type', 'external_origin_reference', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function context(): BelongsTo
    {
        return $this->belongsTo(CommercialContext::class, 'context_id');
    }
}
