<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fournisseur (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §7) : relation commerciale locale
 * scoppée au contexte, jamais une identité GAMAD/DG Afrique. external_origin_* préparent une
 * intégration future (V2) mais restent NULL tant qu'aucune n'existe réellement (§7.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('display_name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->string('external_origin_type')->nullable();
            $table->string('external_origin_reference')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->index(['context_id', 'active']);
        });

        // Les deux champs d'origine externe doivent être renseignés ensemble ou pas du tout
        // (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §7.1) : jamais l'un sans l'autre.
        DB::statement(
            'ALTER TABLE suppliers ADD CONSTRAINT suppliers_external_origin_pair_check '.
            'CHECK ((external_origin_type IS NULL) = (external_origin_reference IS NULL))'
        );
        DB::statement(
            'CREATE UNIQUE INDEX suppliers_context_external_origin_unique ON suppliers '.
            '(context_id, external_origin_type, external_origin_reference) '.
            'WHERE external_origin_type IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
