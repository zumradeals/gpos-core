<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §12). CASH uniquement pour
 * LOT-001. sale_id unique : un seul paiement par vente (pas de paiement partiel dans LOT-001) —
 * garantie structurelle contre un double encaissement au retry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->uuid('sale_id');
            $table->string('method', 20)->default('CASH');
            $table->unsignedBigInteger('amount_xof');
            $table->string('status', 20)->default('CONFIRMED');
            $table->string('actor_core_reference');
            $table->timestampTz('paid_at');
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->unique('sale_id');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('CASH'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('CONFIRMED'))");
        DB::statement('CREATE UNIQUE INDEX payments_idempotency_key_unique ON payments (idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
