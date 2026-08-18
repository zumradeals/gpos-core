<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteurs de numérotation par contexte (référence de vente, numéro de document). Incrémentés
 * sous verrou de ligne pour éviter toute collision — voir App\Domain\Commerce\SequenceGenerator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_context_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('sequence_type', 30);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->unique(['context_id', 'sequence_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_context_sequences');
    }
};
