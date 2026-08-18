<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CurrentActor;
use App\Infrastructure\Identity\DevCoreSessionGateway;
use App\Livewire\Sell;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\CommercialDocument;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Couvre les tests UX/HTTP de docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §23.
 */
final class CommerceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_accessible_with_an_active_context(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-HOME', CommercialPermission::all());

        $this->signIn('IDN-HOME')
            ->get('/')
            ->assertOk()
            ->assertSee($context->display_name);
    }

    public function test_the_absence_of_a_commercial_context_shows_an_explicit_screen(): void
    {
        // Aucune adhésion à aucun contexte pour cette référence.
        $response = $this->signIn('IDN-NO-CONTEXT')->get('/');

        $response->assertStatus(409);
        $response->assertSee('Quelle activité voulez-vous ouvrir');
    }

    public function test_the_catalog_shows_a_clear_empty_state(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-EMPTY-CATALOG', [CommercialPermission::MANAGE_CATALOG]);

        $this->signIn('IDN-EMPTY-CATALOG')
            ->get('/produits')
            ->assertOk()
            ->assertSee('Aucun produit pour le moment');
    }

    public function test_a_product_can_be_created_from_the_catalog_screen(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-CREATOR', [CommercialPermission::MANAGE_CATALOG]);

        $this->signIn('IDN-CREATOR')
            ->post('/produits', [
                'name' => 'Bidon d’huile 5L',
                'kind' => Product::KIND_PRODUCT,
                'sale_price_xof' => 4500,
                'unit_label' => 'bidon',
            ])
            ->assertRedirect('/produits');

        self::assertSame(1, Product::query()->where('context_id', $context->id)->where('name', 'Bidon d’huile 5L')->count());
    }

    public function test_the_sell_pay_confirm_and_receipt_journey_works_end_to_end(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-SELLER', [CommercialPermission::SELL, CommercialPermission::VIEW_DOCUMENTS]);
        $product = $this->product($context, ['name' => 'Boisson locale', 'sale_price_xof' => 700]);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => 25]);

        // La page se charge d'abord réellement en HTTP (parcours réel, pas seulement le composant).
        $this->signIn('IDN-SELLER')->get('/vendre')->assertOk()->assertSee('Boisson locale');

        $identity = new CoreIdentityReference('IDN-SELLER');
        $member = CommercialContextMember::query()->where('context_id', $context->id)->where('core_identity_reference', 'IDN-SELLER')->sole();
        app()->instance(CurrentActor::class, (new CurrentActor($identity))->withActiveContext($context, $member->permissions));

        Livewire::test(Sell::class)
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id)
            ->assertSet('errorMessage', null)
            ->call('confirmCash');

        $document = CommercialDocument::query()->where('context_id', $context->id)->sole();
        self::assertSame(1400, $document->snapshot['total_xof']);

        $this->signIn('IDN-SELLER')
            ->get('/documents/'.$document->id)
            ->assertOk()
            ->assertSee($document->number)
            ->assertSee('1 400');
    }

    public function test_validation_errors_are_human_readable(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-VALIDATOR', [CommercialPermission::MANAGE_CATALOG]);

        $this->signIn('IDN-VALIDATOR')
            ->post('/produits', ['name' => '', 'kind' => 'PRODUCT', 'sale_price_xof' => -5])
            ->assertSessionHasErrors(['name', 'sale_price_xof']);
    }

    public function test_the_interface_adapts_to_the_actors_permission(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-NO-SELL', [CommercialPermission::MANAGE_CATALOG]);

        $home = $this->signIn('IDN-NO-SELL')->get('/');
        $home->assertOk();
        $home->assertDontSee('href="'.route('sell.show', absolute: false).'"', false);

        $this->signIn('IDN-NO-SELL')->get('/vendre')->assertForbidden();
    }

    private function signIn(string $reference): self
    {
        return $this->withSession([DevCoreSessionGateway::SESSION_KEY => $reference]);
    }

    private function context(array $overrides = []): CommercialContext
    {
        return CommercialContext::query()->create(array_replace([
            'display_name' => 'Comptoir de test',
            'currency' => 'XOF',
            'timezone' => 'Africa/Abidjan',
            'status' => CommercialContext::STATUS_ACTIVE,
        ], $overrides));
    }

    private function member(CommercialContext $context, string $reference, array $permissions): CommercialContextMember
    {
        return CommercialContextMember::query()->create([
            'context_id' => $context->id,
            'core_identity_reference' => $reference,
            'permissions' => $permissions,
            'status' => CommercialContextMember::STATUS_ACTIVE,
        ]);
    }

    private function product(CommercialContext $context, array $overrides = []): Product
    {
        return Product::query()->create(array_replace([
            'context_id' => $context->id,
            'name' => 'Produit de test',
            'kind' => Product::KIND_PRODUCT,
            'sale_price_xof' => 1000,
            'track_stock' => true,
            'active' => true,
            'unit_label' => 'unité',
        ], $overrides));
    }
}
