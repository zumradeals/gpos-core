<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vente (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §11). Montants en XOF entier.
 * idempotency_key unique : une confirmation rejouée avec la même clé ne mute jamais deux fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->unsignedBigInteger('subtotal_xof')->default(0);
            $table->unsignedBigInteger('discount_xof')->default(0);
            $table->unsignedBigInteger('total_xof')->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->string('created_by_core_reference');
            $table->string('confirmed_by_core_reference')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->string('client_reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->index(['context_id', 'status']);
        });

        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('DRAFT','CONFIRMED','CANCELLED'))");
        DB::statement('CREATE UNIQUE INDEX sales_context_reference_unique ON sales (context_id, reference) WHERE reference IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX sales_idempotency_key_unique ON sales (idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
