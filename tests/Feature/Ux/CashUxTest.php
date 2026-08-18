<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CurrentActor;
use App\Infrastructure\Identity\DevCoreSessionGateway;
use App\Livewire\Sell;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\CommercialDocument;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Couvre les tests UX/HTTP de docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §36.
 */
final class CashUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_caisse_is_hidden_without_any_relevant_permission(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-NO-CASH', [CommercialPermission::SELL]);

        $home = $this->signIn('IDN-NO-CASH')->get('/');
        $home->assertOk();
        $home->assertDontSee('href="'.route('cash.hub', absolute: false).'"', false);

        $this->signIn('IDN-NO-CASH')->get('/caisse')->assertForbidden();
    }

    public function test_the_cash_hub_shows_an_honest_empty_state(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-MANAGER', [CommercialPermission::MANAGE_CASH, CommercialPermission::VIEW_CASH]);
        $this->member($context, 'IDN-VIEWER', [CommercialPermission::VIEW_CASH]);

        $this->signIn('IDN-MANAGER')
            ->get('/caisse')
            ->assertOk()
            ->assertSee('Créez votre première caisse')
            ->assertSee('Créer une caisse');

        // Un acteur sans MANAGE_CASH voit un état vide honnête, jamais un faux bouton (§22.1).
        $this->signIn('IDN-VIEWER')
            ->get('/caisse')
            ->assertOk()
            ->assertSee('Créez votre première caisse')
            ->assertDontSee('Créer une caisse');
    }

    public function test_a_register_can_be_created_from_the_ui(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-CREATOR', [CommercialPermission::MANAGE_CASH, CommercialPermission::VIEW_CASH]);

        $this->signIn('IDN-CREATOR')
            ->post('/caisse/registres', ['name' => 'Caisse principale'])
            ->assertRedirect('/caisse');

        self::assertSame(1, CashRegister::query()->where('context_id', $context->id)->where('name', 'Caisse principale')->count());
    }

    public function test_a_session_can_be_opened_with_initial_funds_via_the_ui(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-OPENER', [CommercialPermission::OPERATE_CASH, CommercialPermission::VIEW_CASH]);
        $register = $this->register($context);

        $this->signIn('IDN-OPENER')
            ->post('/caisse/registres/'.$register->id.'/ouvrir', ['opening_amount_xof' => 15000])
            ->assertRedirect('/caisse');

        $session = CashSession::query()->where('cash_register_id', $register->id)->sole();
        self::assertSame(CashSession::STATUS_OPEN, $session->status);
        self::assertSame(15000, $session->opening_amount_xof);

        $this->signIn('IDN-OPENER')
            ->get('/caisse')
            ->assertOk()
            ->assertSee('Espèces attendues')
            ->assertSee('15 000');
    }

    public function test_a_cash_sale_with_a_closed_register_shows_a_human_instruction(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-CLOSED-REGISTER', [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);
        $product = $this->product($context, ['sale_price_xof' => 500]);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => 5]);

        $this->currentActor($context, 'IDN-CLOSED-REGISTER', [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);

        Livewire::test(Sell::class)
            ->call('addProduct', (string) $product->id)
            ->call('confirmCash')
            ->assertSee('Ouvrez votre caisse avant d’encaisser en espèces.')
            ->assertSee('Ouvrir ma caisse');
    }

    public function test_an_open_session_shows_the_expected_balance_and_recent_movements(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-SESSION-VIEW', [CommercialPermission::OPERATE_CASH]);
        $register = $this->register($context);
        $actor = $this->currentActor($context, 'IDN-SESSION-VIEW');

        $this->signIn('IDN-SESSION-VIEW')->post('/caisse/registres/'.$register->id.'/ouvrir', ['opening_amount_xof' => 2000])->assertRedirect();
        $this->signIn('IDN-SESSION-VIEW')
            ->post('/caisse/mouvements', ['direction' => 'IN', 'amount_xof' => 500, 'reason' => 'Appoint de caisse'])
            ->assertRedirect();

        $this->signIn('IDN-SESSION-VIEW')
            ->get('/caisse')
            ->assertOk()
            ->assertSee('Espèces attendues')
            ->assertSee('Appoint de caisse')
            ->assertSee('Fonds de départ');
    }

    public function test_a_manual_movement_works_through_a_real_http_request(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-MANUAL', [CommercialPermission::OPERATE_CASH]);
        $register = $this->register($context);

        $this->signIn('IDN-MANUAL')->post('/caisse/registres/'.$register->id.'/ouvrir', ['opening_amount_xof' => 500])->assertRedirect();

        $this->signIn('IDN-MANUAL')
            ->post('/caisse/mouvements', ['direction' => 'OUT', 'amount_xof' => 300, 'reason' => 'Achat de sacs plastiques'])
            ->assertRedirect('/caisse');

        $movement = CashMovement::query()->where('context_id', $context->id)->where('movement_type', CashMovement::TYPE_MANUAL_OUT)->sole();
        self::assertSame(300, $movement->amount_xof);
    }

    public function test_a_balanced_closure_works_through_the_ui(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-CLOSER', [CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);
        $register = $this->register($context);

        $this->signIn('IDN-CLOSER')->post('/caisse/registres/'.$register->id.'/ouvrir', ['opening_amount_xof' => 4000])->assertRedirect();

        $this->signIn('IDN-CLOSER')->get('/caisse/cloturer')->assertOk()->assertSee('Espèces attendues');

        $this->signIn('IDN-CLOSER')
            ->post('/caisse/cloturer', ['counted_amount_xof' => 4000])
            ->assertRedirect('/caisse');

        $session = CashSession::query()->where('cash_register_id', $register->id)->sole();
        self::assertSame(CashSession::STATUS_CLOSED, $session->status);
    }

    public function test_a_closure_with_variance_demands_a_reason(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-VARIANCE', [CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);
        $register = $this->register($context);

        $this->signIn('IDN-VARIANCE')->post('/caisse/registres/'.$register->id.'/ouvrir', ['opening_amount_xof' => 4000])->assertRedirect();

        $this->signIn('IDN-VARIANCE')
            ->post('/caisse/cloturer', ['counted_amount_xof' => 3500])
            ->assertRedirect('/caisse/cloturer');

        $session = CashSession::query()->where('cash_register_id', $register->id)->sole();
        self::assertSame(CashSession::STATUS_OPEN, $session->status, 'Un écart sans motif ne doit pas clôturer la session.');

        $this->signIn('IDN-VARIANCE')
            ->post('/caisse/cloturer', ['counted_amount_xof' => 3500, 'variance_reason' => 'Erreur de comptage matinale'])
            ->assertRedirect('/caisse');

        self::assertSame(CashSession::STATUS_CLOSED_WITH_VARIANCE, $session->fresh()->status);
    }

    public function test_opening_a_session_for_a_register_from_another_context_is_refused(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $this->member($contextA, 'IDN-CROSS-OPEN', [CommercialPermission::OPERATE_CASH]);
        $foreignRegister = $this->register($contextB);

        $this->signIn('IDN-CROSS-OPEN')
            ->post('/caisse/registres/'.$foreignRegister->id.'/ouvrir', ['opening_amount_xof' => 0])
            ->assertNotFound();

        self::assertSame(0, CashSession::query()->where('cash_register_id', $foreignRegister->id)->count());
    }

    public function test_a_cash_closure_document_is_accessible_only_with_permission_and_context(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $this->member($contextA, 'IDN-DOC-OWNER', [
            CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH, CommercialPermission::VIEW_DOCUMENTS,
        ]);
        $this->member($contextB, 'IDN-DOC-OUTSIDER', [CommercialPermission::VIEW_DOCUMENTS]);
        $this->member($contextA, 'IDN-DOC-NO-PERM', [CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);
        $register = $this->register($contextA);

        $this->signIn('IDN-DOC-OWNER')->post('/caisse/registres/'.$register->id.'/ouvrir', ['opening_amount_xof' => 0])->assertRedirect();
        $this->signIn('IDN-DOC-OWNER')->post('/caisse/cloturer', ['counted_amount_xof' => 0])->assertRedirect();

        $document = CommercialDocument::query()->where('context_id', $contextA->id)->where('document_type', CommercialDocument::TYPE_CASH_CLOSURE)->sole();

        $this->signIn('IDN-DOC-OWNER')->get('/documents/'.$document->id)->assertOk()->assertSee($document->number);
        $this->signIn('IDN-DOC-OUTSIDER')->get('/documents/'.$document->id)->assertNotFound();
        $this->signIn('IDN-DOC-NO-PERM')->get('/documents/'.$document->id)->assertForbidden();
    }

    public function test_mobile_keeps_vendre_central_and_caisse_stays_reachable(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-MOBILE-NAV', [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);

        $home = $this->signIn('IDN-MOBILE-NAV')->get('/');

        $home->assertOk();
        $home->assertSee('gp-tabbar__sell', false);
        $home->assertSee('href="'.route('cash.hub').'"', false);
        $home->assertSee('Caisse');
    }

    private function signIn(string $reference): self
    {
        return $this->withSession([DevCoreSessionGateway::SESSION_KEY => $reference]);
    }

    private function currentActor(CommercialContext $context, string $reference, array $permissions = [CommercialPermission::OPERATE_CASH]): CurrentActor
    {
        $identity = new CoreIdentityReference($reference);
        $member = CommercialContextMember::query()->firstOrCreate(
            ['context_id' => $context->id, 'core_identity_reference' => $reference],
            ['permissions' => $permissions, 'status' => CommercialContextMember::STATUS_ACTIVE],
        );

        $actor = (new CurrentActor($identity))->withActiveContext($context, $member->permissions);
        app()->instance(CurrentActor::class, $actor);

        return $actor;
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

    private function register(CommercialContext $context, array $overrides = []): CashRegister
    {
        return CashRegister::query()->create(array_replace([
            'context_id' => $context->id,
            'name' => 'Caisse de test',
            'status' => CashRegister::STATUS_ACTIVE,
            'created_by_core_reference' => 'IDN-SEED',
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
}
