<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ligne de commande d'achat (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §10). Nom/unité
 * snapshotés à l'ajout. Un même produit n'apparaît qu'une fois par commande (§10.1) — garantie
 * structurelle par index unique. received_quantity <= ordered_quantity est vérifié applicativement
 * par ReceivePurchaseOrder sous verrou de ligne, pas seulement en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('purchase_order_id');
            $table->uuid('product_id')->nullable();
            $table->string('product_name_snapshot');
            $table->string('unit_label_snapshot');
            $table->unsignedBigInteger('unit_cost_xof');
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->unsignedBigInteger('line_total_xof');
            $table->boolean('track_stock_snapshot')->default(true);
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->unique(['purchase_order_id', 'product_id']);
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
