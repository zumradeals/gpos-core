<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §8) : appartient à un seul
 * contexte, jamais supprimée physiquement via l'UX LOT-003 — une suspension ne supprime jamais
 * l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->string('created_by_core_reference');
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->index(['context_id', 'status']);
        });

        DB::statement("ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_status_check CHECK (status IN ('ACTIVE','SUSPENDED'))");
        DB::statement('CREATE UNIQUE INDEX cash_registers_context_code_unique ON cash_registers (context_id, code) WHERE code IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
