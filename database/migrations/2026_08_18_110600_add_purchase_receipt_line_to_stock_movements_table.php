<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Étend stock_movements pour les entrées de réception (docs/implementation/LOT-002-PURCHASING-
 * SUPPLY.md §14.1) sans toucher aux mouvements de vente LOT-001. Index unique partiel : une même
 * ligne de réception ne produit jamais qu'un seul mouvement de stock — garantie structurelle
 * contre le double stock au retry, symétrique à celle déjà en place sur sale_line_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->uuid('purchase_receipt_line_id')->nullable()->after('sale_line_id');
            $table->foreign('purchase_receipt_line_id')->references('id')->on('purchase_receipt_lines')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX stock_movements_purchase_receipt_line_unique ON stock_movements (purchase_receipt_line_id) WHERE purchase_receipt_line_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['purchase_receipt_line_id']);
            $table->dropColumn('purchase_receipt_line_id');
        });
    }
};
