<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ligne de vente — nom et prix snapshotés à l'ajout, jamais recalculés depuis le produit courant
 * (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §11.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->uuid('product_id')->nullable();
            $table->string('product_name_snapshot');
            $table->string('unit_label_snapshot');
            $table->unsignedBigInteger('unit_price_xof');
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('line_total_xof');
            $table->boolean('track_stock_snapshot')->default(true);
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
    }
};
