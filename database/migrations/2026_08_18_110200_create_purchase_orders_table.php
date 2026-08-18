<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commande d'achat (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §9). Montants en XOF entier.
 * Une commande DRAFT est modifiable ; après ORDERED, fournisseur/lignes/quantités/coûts snapshots
 * sont immuables (§10.1). confirmation_idempotency_key unique : une confirmation rejouée avec la
 * même clé ne mute jamais deux fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->uuid('supplier_id');
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->string('currency', 3)->default('XOF');
            $table->string('supplier_display_name_snapshot')->nullable();
            $table->unsignedBigInteger('subtotal_xof')->default(0);
            $table->unsignedBigInteger('total_xof')->default(0);
            $table->date('expected_on')->nullable();
            $table->text('note')->nullable();
            $table->string('created_by_core_reference');
            $table->string('ordered_by_core_reference')->nullable();
            $table->timestampTz('ordered_at')->nullable();
            $table->string('cancelled_by_core_reference')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('confirmation_idempotency_key')->nullable();
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->index(['context_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check '.
            "CHECK (status IN ('DRAFT','ORDERED','PARTIALLY_RECEIVED','RECEIVED','CANCELLED'))"
        );
        DB::statement('CREATE UNIQUE INDEX purchase_orders_context_reference_unique ON purchase_orders (context_id, reference) WHERE reference IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX purchase_orders_idempotency_key_unique ON purchase_orders (confirmation_idempotency_key) WHERE confirmation_idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
