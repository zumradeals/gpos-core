<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use App\Application\Commerce\ConfirmPurchaseOrder;
use App\Application\Commerce\PurchaseOrderDraftService;
use App\Application\Commerce\SupplierManager;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CurrentActor;
use App\Infrastructure\Identity\DevCoreSessionGateway;
use App\Livewire\PurchaseOrderBuilder;
use App\Livewire\ReceivePurchaseOrder;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\CommercialDocument;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Couvre les tests UX/HTTP de docs/implementation/LOT-002-PURCHASING-SUPPLY.md §29.
 */
final class PurchasingUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_acheter_is_hidden_without_any_relevant_permission(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-NO-PURCHASE', [CommercialPermission::SELL]);

        $home = $this->signIn('IDN-NO-PURCHASE')->get('/');
        $home->assertOk();
        $home->assertDontSee('href="'.route('purchases.hub', absolute: false).'"', false);

        $this->signIn('IDN-NO-PURCHASE')->get('/acheter')->assertForbidden();
    }

    public function test_the_purchasing_hub_shows_a_clear_empty_state(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-EMPTY-HUB', [CommercialPermission::VIEW_PURCHASES]);

        $this->signIn('IDN-EMPTY-HUB')
            ->get('/acheter')
            ->assertOk()
            ->assertSee('Aucun achat pour le moment');
    }

    public function test_a_supplier_can_be_created_from_the_suppliers_screen(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-SUPPLIER-CREATOR', [CommercialPermission::MANAGE_PURCHASES, CommercialPermission::VIEW_PURCHASES]);

        $this->signIn('IDN-SUPPLIER-CREATOR')
            ->post('/acheter/fournisseurs', ['display_name' => 'Grossiste Nord'])
            ->assertRedirect('/acheter/fournisseurs');

        self::assertSame(1, Supplier::query()->where('context_id', $context->id)->where('display_name', 'Grossiste Nord')->count());
    }

    public function test_the_full_purchase_journey_works_end_to_end(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-BUYER', [
            CommercialPermission::MANAGE_PURCHASES,
            CommercialPermission::VIEW_PURCHASES,
            CommercialPermission::RECEIVE_PURCHASES,
            CommercialPermission::PAY_PURCHASES,
            CommercialPermission::VIEW_DOCUMENTS,
        ]);
        $product = $this->product($context, ['name' => 'Sac de riz 25kg']);

        // La commande démarre réellement en HTTP (choix du fournisseur), pas seulement en composant.
        $this->signIn('IDN-BUYER')->post('/acheter/fournisseurs', ['display_name' => 'Grossiste Sud'])->assertRedirect();
        $supplier = Supplier::query()->where('context_id', $context->id)->sole();

        $create = $this->signIn('IDN-BUYER')->get('/acheter/nouveau');
        $create->assertOk()->assertSee('Grossiste Sud');

        $storeResponse = $this->signIn('IDN-BUYER')->post('/acheter/commandes', ['supplier_id' => $supplier->id]);
        $storeResponse->assertRedirect();
        $order = PurchaseOrder::query()->where('context_id', $context->id)->sole();

        $this->signIn('IDN-BUYER')->get('/acheter/commandes/'.$order->id)->assertOk()->assertSee('Sac de riz 25kg');

        $identity = new CoreIdentityReference('IDN-BUYER');
        $member = CommercialContextMember::query()->where('context_id', $context->id)->where('core_identity_reference', 'IDN-BUYER')->sole();
        app()->instance(CurrentActor::class, (new CurrentActor($identity))->withActiveContext($context, $member->permissions));

        Livewire::test(PurchaseOrderBuilder::class, ['purchaseOrderId' => (string) $order->id])
            ->call('selectProduct', (string) $product->id)
            ->set('quantity', '10')
            ->set('unitCostXof', '1200')
            ->call('addLine')
            ->assertSet('errorMessage', null)
            ->call('confirmOrder');

        $confirmed = $order->fresh();
        self::assertSame(PurchaseOrder::STATUS_ORDERED, $confirmed->status);
        self::assertSame(12000, $confirmed->total_xof);

        $document = CommercialDocument::query()->where('purchase_order_id', $order->id)->sole();
        $this->signIn('IDN-BUYER')->get('/documents/'.$document->id)->assertOk()->assertSee($document->number);

        $line = $confirmed->lines()->sole();

        // Réception partielle.
        Livewire::test(ReceivePurchaseOrder::class, ['purchaseOrderId' => (string) $order->id])
            ->set('receiveNow.'.$line->id, '6')
            ->call('confirmReceipt');

        $partial = $order->fresh();
        self::assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $partial->status);

        $this->signIn('IDN-BUYER')->get('/acheter/commandes/'.$order->id)->assertOk()->assertSee('Réception partielle');

        // Réception finale.
        Livewire::test(ReceivePurchaseOrder::class, ['purchaseOrderId' => (string) $order->id])
            ->set('receiveNow.'.$line->id, '4')
            ->call('confirmReceipt');

        $received = $order->fresh();
        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $received->status);

        $goodsReceiptDocument = CommercialDocument::query()->where('document_type', CommercialDocument::TYPE_GOODS_RECEIPT)->latest('issued_at')->firstOrFail();
        $this->signIn('IDN-BUYER')->get('/documents/'.$goodsReceiptDocument->id)->assertOk()->assertSee('Bon de réception');

        // Paiement comptant, autorisé seulement maintenant que la commande est RECEIVED.
        $this->signIn('IDN-BUYER')
            ->post('/acheter/commandes/'.$order->id.'/payer')
            ->assertRedirect();

        $this->signIn('IDN-BUYER')->get('/acheter/commandes/'.$order->id)->assertOk()->assertSee('Payé comptant');
    }

    public function test_over_receipt_produces_a_readable_error(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-OVER', [CommercialPermission::MANAGE_PURCHASES, CommercialPermission::RECEIVE_PURCHASES]);
        $product = $this->product($context);
        $supplier = $this->supplier($context);

        $actor = $this->currentActor($context, 'IDN-OVER');
        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, '5', 1000);
        $confirmed = app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-ux-over-1')->purchaseOrder;
        $line = $confirmed->lines()->sole();

        Livewire::test(ReceivePurchaseOrder::class, ['purchaseOrderId' => (string) $confirmed->id])
            ->set('receiveNow.'.$line->id, '9')
            ->call('confirmReceipt')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, 'restant'));
    }

    public function test_a_purchase_order_from_another_context_is_inaccessible(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $this->member($contextA, 'IDN-CROSS-A', [CommercialPermission::VIEW_PURCHASES]);

        $actorB = $this->currentActor($contextB, 'IDN-CROSS-B', [CommercialPermission::MANAGE_PURCHASES]);
        $supplierB = $this->supplier($contextB);
        $orderB = app(PurchaseOrderDraftService::class)->createDraft($contextB, $actorB->identity, $supplierB);

        $this->signIn('IDN-CROSS-A')->get('/acheter/commandes/'.$orderB->id)->assertNotFound();
    }

    public function test_home_shows_a_real_receipt_to_do_only_for_an_authorized_actor(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-CAN-RECEIVE', [CommercialPermission::RECEIVE_PURCHASES, CommercialPermission::MANAGE_PURCHASES]);
        $this->member($context, 'IDN-CANNOT-RECEIVE', [CommercialPermission::SELL]);

        $supplier = $this->supplier($context);
        $actor = $this->currentActor($context, 'IDN-CAN-RECEIVE');
        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $this->product($context), '4', 1000);
        app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-home-todo-1');

        $this->signIn('IDN-CAN-RECEIVE')->get('/')->assertOk()->assertSee('à réceptionner');
        $this->signIn('IDN-CANNOT-RECEIVE')->get('/')->assertOk()->assertDontSee('à réceptionner');
    }

    private function signIn(string $reference): self
    {
        return $this->withSession([DevCoreSessionGateway::SESSION_KEY => $reference]);
    }

    private function currentActor(CommercialContext $context, string $reference, array $permissions = [CommercialPermission::MANAGE_PURCHASES]): CurrentActor
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

    private function supplier(CommercialContext $context, array $overrides = []): Supplier
    {
        return app(SupplierManager::class)->create($context, new CoreIdentityReference('IDN-SUPPLIER-SEED'), array_replace([
            'display_name' => 'Fournisseur de test',
        ], $overrides));
    }
}
