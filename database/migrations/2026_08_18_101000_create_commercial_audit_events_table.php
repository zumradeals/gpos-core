<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit append-only (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §14).
 * N'est jamais une seconde source transactionnelle (docs/architecture/SATELLITE-CONTRACT.md §9) —
 * lecture seule après écriture, aucune mise à jour ni suppression applicative.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('actor_core_reference');
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->string('aggregate_reference');
            $table->jsonb('before_state')->nullable();
            $table->jsonb('after_state')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->string('request_reference')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->index(['context_id', 'aggregate_type', 'aggregate_reference']);
            $table->index(['context_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_audit_events');
    }
};
