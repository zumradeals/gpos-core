<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ligne de réception (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §13) : snapshot figé de ce
 * qui a été effectivement reçu, indépendant de toute modification ultérieure du produit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipt_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_receipt_id');
            $table->uuid('purchase_order_line_id');
            $table->uuid('product_id')->nullable();
            $table->string('product_name_snapshot');
            $table->string('unit_label_snapshot');
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('unit_cost_xof');
            $table->unsignedBigInteger('line_total_xof');
            $table->boolean('track_stock_snapshot')->default(true);
            $table->timestamps();

            $table->foreign('purchase_receipt_id')->references('id')->on('purchase_receipts')->cascadeOnDelete();
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('purchase_receipt_id');
            $table->index('purchase_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_lines');
    }
};
