<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Généralise payments pour le paiement fournisseur (docs/implementation/LOT-002-PURCHASING-
 * SUPPLY.md §17.1) plutôt que de créer un second moteur financier. sale_id devient nullable ;
 * exactement une source (sale_id XOR purchase_order_id) est imposée en base — jamais les deux,
 * jamais aucune. L'unicité d'un paiement par vente LOT-001 est conservée (sale_id restait unique) ;
 * un paiement par commande d'achat est ajouté symétriquement pour LOT-002.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('sale_id')->nullable()->change();
            $table->uuid('purchase_order_id')->nullable()->after('sale_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
        });

        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_single_source_check '.
            'CHECK (num_nonnulls(sale_id, purchase_order_id) = 1)'
        );
        DB::statement('CREATE UNIQUE INDEX payments_purchase_order_unique ON payments (purchase_order_id) WHERE purchase_order_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_single_source_check');
        DB::statement('DROP INDEX IF EXISTS payments_purchase_order_unique');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
            $table->uuid('sale_id')->nullable(false)->change();
        });
    }
};
