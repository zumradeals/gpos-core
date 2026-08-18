<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Session de caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §9). Garanties
 * structurelles (§9.2), pas seulement applicatives : au plus une session OPEN par caisse, au plus
 * une session OPEN par (contexte, responsable), et une closure_idempotency_key jamais dupliquée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->uuid('cash_register_id');
            $table->string('reference')->nullable();
            $table->string('status', 24)->default('OPEN');
            $table->string('responsible_core_reference');
            $table->unsignedBigInteger('opening_amount_xof')->default(0);
            $table->timestampTz('opened_at');
            $table->string('opened_by_core_reference');
            $table->string('opening_idempotency_key')->nullable();
            $table->unsignedBigInteger('counted_amount_xof')->nullable();
            $table->unsignedBigInteger('expected_amount_xof_snapshot')->nullable();
            $table->bigInteger('variance_xof')->nullable();
            $table->text('variance_reason')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('closed_by_core_reference')->nullable();
            $table->string('closure_idempotency_key')->nullable();
            $table->timestamps();

            $table->foreign('context_id')->references('id')->on('commercial_contexts')->cascadeOnDelete();
            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->cascadeOnDelete();
            $table->index(['context_id', 'status']);
        });

        DB::statement("ALTER TABLE cash_sessions ADD CONSTRAINT cash_sessions_status_check CHECK (status IN ('OPEN','CLOSED','CLOSED_WITH_VARIANCE'))");
        DB::statement('CREATE UNIQUE INDEX cash_sessions_context_reference_unique ON cash_sessions (context_id, reference) WHERE reference IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_register ON cash_sessions (cash_register_id) WHERE status = 'OPEN'");
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_responsible ON cash_sessions (context_id, responsible_core_reference) WHERE status = 'OPEN'");
        DB::statement('CREATE UNIQUE INDEX cash_sessions_closure_idempotency_key_unique ON cash_sessions (closure_idempotency_key) WHERE closure_idempotency_key IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cash_sessions_opening_idempotency_key_unique ON cash_sessions (opening_idempotency_key) WHERE opening_idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
