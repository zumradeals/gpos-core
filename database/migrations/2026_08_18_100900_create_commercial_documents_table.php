<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document commercial (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §13). Le snapshot
 * JSON est la vérité figée au moment de l'émission ; modifier un produit ensuite ne le réécrit
 * jamais (docs/architecture/SATELLITE-CONTRACT.md §13). (sale_id, document_type) unique : un seul
 * reçu par vente, structurellement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->uuid('sale_id');
            $table->string('document_type', 20)->default('RECEIPT');
            $table->string('number');
            $table->jsonb('snapshot');
            $table->timestampTz('issued_at');
            $table->string('issued_by_core_reference');
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->unique(['sale_id', 'document_type']);
            $table->unique(['context_id', 'number']);
        });

        DB::statement("ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_type_check CHECK (document_type IN ('RECEIPT'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_documents');
    }
};
