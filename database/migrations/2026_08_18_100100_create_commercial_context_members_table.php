<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôles commerciaux locaux (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §8).
 * N'accordent jamais d'autorité ZUMRA/GAMAD (docs/G-POS-DOCTRINE.md §10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_context_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('core_identity_reference');
            $table->jsonb('permissions')->default('[]');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->unique(['context_id', 'core_identity_reference']);
            $table->index('core_identity_reference');
        });

        DB::statement("ALTER TABLE commercial_context_members ADD CONSTRAINT commercial_context_members_status_check CHECK (status IN ('ACTIVE','SUSPENDED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_context_members');
    }
};
