<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seuil de réassort optionnel (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §8). Ne
 * s'applique qu'aux produits suivis en stock ; ne crée jamais de commande automatique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('reorder_threshold', 14, 3)->nullable()->after('unit_label');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('reorder_threshold');
        });
    }
};
