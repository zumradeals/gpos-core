<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Commerce\ProductCatalog;
use App\Application\Commerce\StockAdjuster;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CoreIdentityReference;
use App\Models\CashRegister;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

/**
 * Amorce un contexte commercial de démonstration pour l'acteur de développement configuré
 * (GPOS_DEV_CORE_IDENTITY_REFERENCE), avec toutes les permissions et deux produits d'exemple.
 * Ne s'exécute jamais implicitement — seulement via `php artisan db:seed --class=DevBootstrapSeeder`
 * en local/testing (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.3).
 */
final class DevBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DevBootstrapSeeder ne peut pas s’exécuter en production.');

            return;
        }

        $reference = config('gpos.dev_identity.core_identity_reference');

        if (! is_string($reference) || $reference === '') {
            $this->command?->error('GPOS_DEV_CORE_IDENTITY_REFERENCE doit être défini dans .env avant d’amorcer.');

            return;
        }

        $identity = new CoreIdentityReference($reference, config('gpos.dev_identity.core_identity_label'));

        $context = CommercialContext::query()->firstOrCreate(
            ['display_name' => 'Boutique de démonstration'],
            ['external_origin_type' => CommercialContext::ORIGIN_STANDALONE, 'currency' => 'XOF', 'timezone' => 'Africa/Abidjan', 'status' => CommercialContext::STATUS_ACTIVE],
        );

        CommercialContextMember::query()->updateOrCreate(
            ['context_id' => $context->id, 'core_identity_reference' => $reference],
            ['permissions' => CommercialPermission::all(), 'status' => CommercialContextMember::STATUS_ACTIVE],
        );

        $catalog = app(ProductCatalog::class);
        $adjuster = app(StockAdjuster::class);

        if (! Product::query()->where('context_id', $context->id)->where('name', 'Sac de riz 25kg')->exists()) {
            $rice = $catalog->create($context, $identity, [
                'name' => 'Sac de riz 25kg', 'sale_price_xof' => 15000, 'kind' => Product::KIND_PRODUCT, 'unit_label' => 'sac',
            ]);
            $adjuster->adjust($context, $identity, $rice, StockMovement::DIRECTION_IN, '20', 'Réception initiale de démonstration');
        }

        if (! Product::query()->where('context_id', $context->id)->where('name', 'Savon local')->exists()) {
            $soap = $catalog->create($context, $identity, [
                'name' => 'Savon local', 'sale_price_xof' => 500, 'kind' => Product::KIND_PRODUCT, 'unit_label' => 'unité',
            ]);
            $adjuster->adjust($context, $identity, $soap, StockMovement::DIRECTION_IN, '50', 'Réception initiale de démonstration');
        }

        if (! Product::query()->where('context_id', $context->id)->where('name', 'Réparation à domicile')->exists()) {
            $catalog->create($context, $identity, [
                'name' => 'Réparation à domicile', 'sale_price_xof' => 3000, 'kind' => Product::KIND_SERVICE, 'unit_label' => 'intervention',
            ]);
        }

        // docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §34 : une caisse de démonstration
        // réelle simplifie le smoke test, jamais créée silencieusement en production (double
        // verrou déjà appliqué en tête de méthode).
        CashRegister::query()->firstOrCreate(
            ['context_id' => $context->id, 'name' => 'Caisse principale'],
            ['status' => CashRegister::STATUS_ACTIVE, 'created_by_core_reference' => $reference],
        );

        $this->command?->info("Contexte « {$context->display_name} » prêt pour {$reference}.");
    }
}
