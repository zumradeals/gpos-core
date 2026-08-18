<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Généralise commercial_documents pour les documents d'achat (docs/implementation/LOT-002-
 * PURCHASING-SUPPLY.md §18.1) plutôt que de créer une seconde infrastructure documentaire.
 * sale_id devient nullable ; exactement une source métier (sale_id XOR purchase_order_id XOR
 * purchase_receipt_id) est imposée en base. Les garanties uniques par vente/type LOT-001 sont
 * conservées ; des garanties symétriques par commande/type et par réception/type sont ajoutées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->uuid('sale_id')->nullable()->change();
            $table->uuid('purchase_order_id')->nullable()->after('sale_id');
            $table->uuid('purchase_receipt_id')->nullable()->after('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('purchase_receipt_id')->references('id')->on('purchase_receipts')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE commercial_documents DROP CONSTRAINT IF EXISTS commercial_documents_type_check');
        DB::statement("ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_type_check CHECK (document_type IN ('RECEIPT','PURCHASE_ORDER','GOODS_RECEIPT'))");

        DB::statement(
            'ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_single_source_check '.
            'CHECK (num_nonnulls(sale_id, purchase_order_id, purchase_receipt_id) = 1)'
        );

        DB::statement('CREATE UNIQUE INDEX commercial_documents_purchase_order_type_unique ON commercial_documents (purchase_order_id, document_type) WHERE purchase_order_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX commercial_documents_purchase_receipt_type_unique ON commercial_documents (purchase_receipt_id, document_type) WHERE purchase_receipt_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE commercial_documents DROP CONSTRAINT IF EXISTS commercial_documents_single_source_check');
        DB::statement('DROP INDEX IF EXISTS commercial_documents_purchase_order_type_unique');
        DB::statement('DROP INDEX IF EXISTS commercial_documents_purchase_receipt_type_unique');
        DB::statement('ALTER TABLE commercial_documents DROP CONSTRAINT IF EXISTS commercial_documents_type_check');
        DB::statement("ALTER TABLE commercial_documents ADD CONSTRAINT commercial_documents_type_check CHECK (document_type IN ('RECEIPT'))");

        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropForeign(['purchase_order_id']);
            $table->dropForeign(['purchase_receipt_id']);
            $table->dropColumn(['purchase_order_id', 'purchase_receipt_id']);
            $table->uuid('sale_id')->nullable(false)->change();
        });
    }
};
