<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réception fournisseur (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §13) : preuve immutable
 * créée par ReceivePurchaseOrder, pas de DRAFT caché nécessaire pour LOT-002. idempotency_key
 * unique : un retry avec la même clé ne crée jamais un second reçu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->uuid('purchase_order_id');
            $table->string('reference');
            $table->string('received_by_core_reference');
            $table->timestampTz('received_at');
            $table->text('note')->nullable();
            $table->string('idempotency_key');
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->index('purchase_order_id');
            $table->unique(['context_id', 'reference']);
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
    }
};
