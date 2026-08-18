<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Application\Commerce\ConfirmCashSale;
use App\Application\Commerce\OpenCashSession;
use App\Application\Commerce\SaleDraftService;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CurrentActor;
use App\Livewire\Sell;
use App\Models\CashRegister;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Revue LOT-001 — BLOQUEUR 1 : isolation contexte + immutabilité vente. SaleDraftService protège
 * ses propres invariants indépendamment de l'appelant (Livewire ou autre) ; App\Livewire\Sell
 * revalide en plus le contexte actif courant à chaque appel (sale()).
 */
final class SaleDraftIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_from_another_context_cannot_be_added_to_a_sale(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $actor = $this->actor($contextA, 'IDN-A', [CommercialPermission::SELL]);
        $sale = app(SaleDraftService::class)->findOrCreateDraft($contextA, $actor->identity);
        $foreignProduct = $this->product($contextB);

        $this->expectException(HttpException::class);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $foreignProduct);
    }

    public function test_a_line_belonging_to_a_different_sale_cannot_have_its_quantity_changed(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, 'IDN-B', [CommercialPermission::SELL]);
        $ownSale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);

        $otherSale = Sale::query()->create(['context_id' => $context->id, 'status' => Sale::STATUS_DRAFT, 'currency' => 'XOF', 'created_by_core_reference' => 'IDN-OTHER']);
        $product = $this->product($context);
        $foreignLine = app(SaleDraftService::class)->addOrIncrementLine($otherSale, $product);

        $this->expectException(HttpException::class);
        app(SaleDraftService::class)->setQuantity($ownSale, $foreignLine, '5');
    }

    public function test_a_line_belonging_to_a_different_sale_cannot_be_removed(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, 'IDN-C', [CommercialPermission::SELL]);
        $ownSale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);

        $otherSale = Sale::query()->create(['context_id' => $context->id, 'status' => Sale::STATUS_DRAFT, 'currency' => 'XOF', 'created_by_core_reference' => 'IDN-OTHER']);
        $product = $this->product($context);
        $foreignLine = app(SaleDraftService::class)->addOrIncrementLine($otherSale, $product);

        $this->expectException(HttpException::class);
        app(SaleDraftService::class)->removeLine($ownSale, $foreignLine);
    }

    public function test_a_confirmed_sale_refuses_every_draft_mutation(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, 'IDN-D', [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);
        $this->openCashSession($context, $actor);
        $product = $this->product($context, ['sale_price_xof' => 1000]);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => 10]);

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        $line = app(SaleDraftService::class)->addOrIncrementLine($sale, $product, '2');
        app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-lock-1');

        $confirmed = $sale->fresh();

        $blocked = 0;
        foreach (['addOrIncrementLine' => fn () => app(SaleDraftService::class)->addOrIncrementLine($confirmed, $product), 'setQuantity' => fn () => app(SaleDraftService::class)->setQuantity($confirmed, $line, '3'), 'removeLine' => fn () => app(SaleDraftService::class)->removeLine($confirmed, $line)] as $attempt) {
            try {
                $attempt();
            } catch (HttpException $e) {
                self::assertSame(409, $e->getStatusCode());
                $blocked++;
            }
        }

        self::assertSame(3, $blocked, 'add/set/remove doivent toutes les trois être refusées après confirmation.');
        self::assertSame(1, $sale->lines()->count(), 'la ligne confirmée ne doit pas avoir été altérée.');
    }

    public function test_a_cancelled_sale_refuses_draft_mutations(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, 'IDN-E', [CommercialPermission::SELL]);
        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        $sale->update(['status' => Sale::STATUS_CANCELLED]);
        $product = $this->product($context);

        $this->expectException(HttpException::class);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product);
    }

    public function test_the_sell_component_fails_closed_when_the_active_context_changes_underneath(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $identity = new CoreIdentityReference('IDN-SWITCH');

        $memberA = $this->member($contextA, 'IDN-SWITCH', [CommercialPermission::SELL]);
        $this->member($contextB, 'IDN-SWITCH', [CommercialPermission::SELL]);

        app()->instance(CurrentActor::class, (new CurrentActor($identity))->withActiveContext($contextA, $memberA->permissions));
        $product = $this->product($contextA);
        StockBalance::query()->create(['context_id' => $contextA->id, 'product_id' => $product->id, 'quantity' => 5]);

        $component = Livewire::test(Sell::class);

        // La vente a été créée dans le contexte A. L'acteur bascule maintenant vers le contexte B
        // (p. ex. un autre onglet) : le composant doit refuser de continuer à lire/muter cette
        // vente plutôt que de supposer que rien n'a changé depuis mount(). incrementLine() passe
        // par sale() avant de toucher à la ligne — c'est cette revalidation qui doit échouer ici
        // (contrairement à addProduct(), dont la requête produit déjà scopée au contexte échouerait
        // la première et masquerait le comportement qu'on veut réellement vérifier).
        $memberB = CommercialContextMember::query()->where('context_id', $contextB->id)->where('core_identity_reference', 'IDN-SWITCH')->sole();
        app()->instance(CurrentActor::class, (new CurrentActor($identity))->withActiveContext($contextB, $memberB->permissions));

        // Livewire::test() route les appels via une vraie sous-requête HTTP interne
        // (Testable::update() -> RequestBroker) qui laisse le gestionnaire d'exceptions Laravel
        // convertir HttpException en réponse HTTP normale plutôt que de la relancer en PHP : on
        // vérifie donc le statut de la réponse, pas une exception attrapée.
        $component->call('incrementLine', (string) Str::uuid())
            ->assertStatus(404);
    }

    private function context(array $overrides = []): CommercialContext
    {
        return CommercialContext::query()->create(array_replace([
            'display_name' => 'Contexte de test',
            'currency' => 'XOF',
            'timezone' => 'Africa/Abidjan',
            'status' => CommercialContext::STATUS_ACTIVE,
        ], $overrides));
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

    private function member(CommercialContext $context, string $reference, array $permissions): CommercialContextMember
    {
        return CommercialContextMember::query()->create([
            'context_id' => $context->id,
            'core_identity_reference' => $reference,
            'permissions' => $permissions,
            'status' => CommercialContextMember::STATUS_ACTIVE,
        ]);
    }

    private function actor(CommercialContext $context, string $reference, array $permissions): CurrentActor
    {
        $member = $this->member($context, $reference, $permissions);
        $actor = (new CurrentActor(new CoreIdentityReference($reference)))->withActiveContext($context, $member->permissions);
        app()->instance(CurrentActor::class, $actor);

        return $actor;
    }

    /**
     * Ouvre une vraie session de caisse pour l'acteur (docs/implementation/LOT-003-CASH-REGISTER-
     * CLOSING.md §36 : interdiction explicite de tout bypass de test pour la caisse).
     */
    private function openCashSession(CommercialContext $context, CurrentActor $actor): void
    {
        $register = CashRegister::query()->create([
            'context_id' => $context->id,
            'name' => 'Caisse de test',
            'status' => CashRegister::STATUS_ACTIVE,
            'created_by_core_reference' => $actor->identity->reference,
        ]);

        app(OpenCashSession::class)->handle($register, $actor, 0, (string) Str::uuid());
    }
}
