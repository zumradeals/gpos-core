<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue minimal (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §9). Prix en XOF
 * entier, jamais float. SKU/code-barres uniques dans le contexte lorsqu'ils sont présents (index
 * uniques partiels : NULL n'entre jamais en collision).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('kind', 10)->default('PRODUCT');
            $table->unsignedBigInteger('sale_price_xof');
            $table->boolean('track_stock')->default(true);
            $table->boolean('active')->default(true);
            $table->string('unit_label')->default('unité');
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->index(['context_id', 'active']);
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_kind_check CHECK (kind IN ('PRODUCT','SERVICE'))");
        DB::statement('CREATE UNIQUE INDEX products_context_sku_unique ON products (context_id, sku) WHERE sku IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX products_context_barcode_unique ON products (context_id, barcode) WHERE barcode IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
