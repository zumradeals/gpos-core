<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Généralise commercial_documents pour le document de clôture de caisse (docs/implementation/
 * LOT-003-CASH-REGISTER-CLOSING.md §18) plutôt que de créer une seconde infrastructure
 * documentaire. La contrainte de source unique existante est mise à jour pour inclure
 * cash_session_id ; jamais deux documents CASH_CLOSURE pour la même session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->uuid('cash_session_id')->nullable()->after('purchase_receipt_id');
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE commercial_documents DROP CONSTRAINT IF EXISTS commercial_documents_single_source_check');
        DB::statement(
            'ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_single_source_check '.
            'CHECK (num_nonnulls(sale_id, purchase_order_id, purchase_receipt_id, cash_session_id) = 1)'
        );

        DB::statement('ALTER TABLE commercial_documents DROP CONSTRAINT IF EXISTS commercial_documents_type_check');
        DB::statement("ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_type_check CHECK (document_type IN ('RECEIPT','PURCHASE_ORDER','GOODS_RECEIPT','CASH_CLOSURE'))");

        DB::statement('CREATE UNIQUE INDEX commercial_documents_cash_session_type_unique ON commercial_documents (cash_session_id, document_type) WHERE cash_session_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS commercial_documents_cash_session_type_unique');
        DB::statement('ALTER TABLE commercial_documents DROP CONSTRAINT IF EXISTS commercial_documents_single_source_check');
        DB::statement('ALTER TABLE commercial_documents DROP CONSTRAINT IF EXISTS commercial_documents_type_check');
        DB::statement("ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_type_check CHECK (document_type IN ('RECEIPT','PURCHASE_ORDER','GOODS_RECEIPT'))");

        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropForeign(['cash_session_id']);
            $table->dropColumn('cash_session_id');
        });

        DB::statement(
            'ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_single_source_check '.
            'CHECK (num_nonnulls(sale_id, purchase_order_id, purchase_receipt_id) = 1)'
        );
    }
};
