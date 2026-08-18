<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registre de caisse append-only (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §11) :
 * jamais modifié ni supprimé applicativement. idempotency_key unique et payment_id unique quand
 * non null garantissent structurellement qu'un paiement CASH confirmé ne produit jamais deux
 * mouvements, et qu'un retry ne double jamais une écriture. Au plus un OPENING_FLOAT par session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->uuid('cash_session_id');
            $table->uuid('payment_id')->nullable();
            $table->string('direction', 3);
            $table->string('movement_type', 20);
            $table->unsignedBigInteger('amount_xof');
            $table->text('reason')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('actor_core_reference');
            $table->timestampTz('occurred_at');
            $table->string('idempotency_key');
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->index(['context_id', 'cash_session_id']);
        });

        DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_direction_check CHECK (direction IN ('IN','OUT'))");
        DB::statement(
            'ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_check '.
            "CHECK (movement_type IN ('OPENING_FLOAT','SALE_PAYMENT','PURCHASE_PAYMENT','MANUAL_IN','MANUAL_OUT'))"
        );
        DB::statement(
            'ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_amount_positive_check '.
            'CHECK (amount_xof > 0)'
        );
        DB::statement(
            'ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_direction_matches_type_check '.
            "CHECK ((movement_type IN ('OPENING_FLOAT','SALE_PAYMENT','MANUAL_IN') AND direction = 'IN') ".
            "OR (movement_type IN ('PURCHASE_PAYMENT','MANUAL_OUT') AND direction = 'OUT'))"
        );

        DB::statement('CREATE UNIQUE INDEX cash_movements_idempotency_key_unique ON cash_movements (idempotency_key)');
        DB::statement('CREATE UNIQUE INDEX cash_movements_payment_unique ON cash_movements (payment_id) WHERE payment_id IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX cash_movements_one_opening_float_per_session ON cash_movements (cash_session_id) WHERE movement_type = 'OPENING_FLOAT'");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
