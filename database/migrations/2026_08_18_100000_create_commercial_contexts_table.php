<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Racine de scope métier (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §7). Toute
 * requête Produit/Vente/Stock/Paiement/Document/Audit est scopée à ce contexte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_contexts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_origin_type', 20)->nullable();
            $table->string('external_origin_reference')->nullable();
            $table->string('display_name');
            $table->string('currency', 3)->default('XOF');
            $table->string('timezone')->default('Africa/Abidjan');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->index('status');
        });

        DB::statement("ALTER TABLE commercial_contexts ADD CONSTRAINT commercial_contexts_external_origin_type_check CHECK (external_origin_type IS NULL OR external_origin_type IN ('ZUMRA','ORGANIZATION','STANDALONE'))");
        DB::statement("ALTER TABLE commercial_contexts ADD CONSTRAINT commercial_contexts_status_check CHECK (status IN ('ACTIVE','SUSPENDED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_contexts');
    }
};
