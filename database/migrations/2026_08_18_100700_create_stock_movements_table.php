<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal métier immutable (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §10). Aucune
 * variation de StockBalance sans mouvement source.
 *
 * sale_line_id est nullable (les mouvements bootstrap/ajustement n'en ont pas) mais unique
 * lorsque présent : garantie STRUCTURELLE (contrainte DB, pas seulement applicative) qu'une même
 * ligne de vente ne génère jamais deux mouvements — un retry avec la même vente confirmée ne peut
 * pas créer un second mouvement OUT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->uuid('product_id');
            $table->uuid('sale_line_id')->nullable();
            $table->string('direction', 12);
            $table->decimal('quantity', 14, 3);
            $table->string('reason')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('actor_core_reference');
            $table->timestampTz('occurred_at');
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('sale_line_id')->references('id')->on('sale_lines')->nullOnDelete();
            $table->index(['context_id', 'product_id']);
        });

        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_direction_check CHECK (direction IN ('IN','OUT','ADJUSTMENT'))");
        DB::statement('CREATE UNIQUE INDEX stock_movements_sale_line_unique ON stock_movements (sale_line_id) WHERE sale_line_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX stock_movements_idempotency_key_unique ON stock_movements (idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
