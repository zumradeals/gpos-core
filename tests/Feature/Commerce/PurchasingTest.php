<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Application\Commerce\CancelPurchaseOrder;
use App\Application\Commerce\ConfirmCashSale;
use App\Application\Commerce\ConfirmPurchaseOrder;
use App\Application\Commerce\OpenCashSession;
use App\Application\Commerce\PurchaseOrderDraftService;
use App\Application\Commerce\ReceivePurchaseOrder;
use App\Application\Commerce\RecordCashPurchasePayment;
use App\Application\Commerce\SaleDraftService;
use App\Application\Commerce\SupplierManager;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\OverReceiptException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotCancellableException;
use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CurrentActor;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CommercialAuditEvent;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\CommercialDocument;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Couvre les tests d'acceptation métier de docs/implementation/LOT-002-PURCHASING-SUPPLY.md §28.
 */
final class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_supplier_is_isolated_by_commercial_context(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $supplierA = $this->supplier($contextA, ['display_name' => 'Fournisseur A']);
        $this->supplier($contextB, ['display_name' => 'Fournisseur B']);

        $visible = Supplier::query()->where('context_id', $contextA->id)->pluck('display_name')->all();

        self::assertSame(['Fournisseur A'], $visible);
        self::assertSame('Fournisseur A', $supplierA->fresh()->display_name);
    }

    public function test_an_actor_without_manage_purchases_cannot_confirm_an_order(): void
    {
        $context = $this->context();
        $supplier = $this->supplier($context);
        $product = $this->product($context);
        $actor = $this->actor($context, []); // aucune permission

        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, '5', 1000);

        $this->expectException(HttpException::class);
        app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-perm-1');
    }

    public function test_a_supplier_from_another_context_is_refused(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $actor = $this->actor($contextA, [CommercialPermission::MANAGE_PURCHASES]);
        $foreignSupplier = $this->supplier($contextB);

        $this->expectException(HttpException::class);
        app(PurchaseOrderDraftService::class)->createDraft($contextA, $actor->identity, $foreignSupplier);
    }

    public function test_a_product_from_another_context_cannot_be_added_to_an_order(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $actor = $this->actor($contextA, [CommercialPermission::MANAGE_PURCHASES]);
        $supplier = $this->supplier($contextA);
        $order = app(PurchaseOrderDraftService::class)->createDraft($contextA, $actor->identity, $supplier);
        $foreignProduct = $this->product($contextB);

        $this->expectException(HttpException::class);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $foreignProduct, '1', 1000);
    }

    public function test_the_line_cost_is_a_strict_integer_computed_without_float(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, [CommercialPermission::MANAGE_PURCHASES]);
        $supplier = $this->supplier($context);
        $product = $this->product($context);
        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);

        $line = app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, '1.250', 1200);

        self::assertIsInt($line->line_total_xof);
        self::assertSame(1500, $line->line_total_xof);
    }

    public function test_a_draft_order_is_modifiable(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, [CommercialPermission::MANAGE_PURCHASES]);
        $supplier = $this->supplier($context);
        $product = $this->product($context);
        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);

        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, '2', 1000);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, '5', 1200);

        self::assertSame(1, $order->lines()->count());
        self::assertSame(6000, $order->fresh()->total_xof);
    }

    public function test_a_confirmed_order_is_immutable(): void
    {
        [$order, $actor] = $this->readyDraftOrder();
        app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-immutable-1');

        $confirmed = $order->fresh();
        $line = $confirmed->lines()->sole();

        $this->expectException(HttpException::class);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($confirmed, $line->product, '9', 1000);
    }

    public function test_confirmation_is_idempotent_and_produces_exactly_one_reference_and_document(): void
    {
        [$order, $actor] = $this->readyDraftOrder();

        $first = app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-confirm-1');
        $second = app(ConfirmPurchaseOrder::class)->handle($order->fresh(), $actor, 'idem-confirm-1');

        self::assertSame($first->purchaseOrder->reference, $second->purchaseOrder->reference);
        self::assertSame($first->document->id, $second->document->id);
        self::assertSame(1, CommercialDocument::query()->where('purchase_order_id', $order->id)->count());
    }

    public function test_partial_receipt_creates_exactly_the_expected_in_movements(): void
    {
        [$order, $actor, $product] = $this->readyOrderedOrder(withProductReturn: true, orderedQuantity: '10');
        $line = $order->fresh()->lines()->sole();

        $result = app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '6'], 'idem-receive-1');

        self::assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $result->purchaseOrder->status);
        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        self::assertSame('6.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
        self::assertSame('4.000', Quantity::subtract((string) $line->fresh()->ordered_quantity, (string) $line->fresh()->received_quantity));
    }

    public function test_final_receipt_moves_the_order_to_received(): void
    {
        [$order, $actor, $product] = $this->readyOrderedOrder(withProductReturn: true, orderedQuantity: '10');
        $line = $order->fresh()->lines()->sole();

        app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '6'], 'idem-receive-a');
        $result = app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '4'], 'idem-receive-b');

        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $result->purchaseOrder->status);
        self::assertSame('0.000', (string) Quantity::subtract((string) $line->fresh()->ordered_quantity, (string) $line->fresh()->received_quantity));
        self::assertSame(2, StockMovement::query()->where('product_id', $product->id)->count());
        self::assertSame('10.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
    }

    public function test_over_receipt_is_refused_atomically(): void
    {
        [$order, $actor, $product] = $this->readyOrderedOrder(withProductReturn: true, orderedQuantity: '10');
        $line = $order->fresh()->lines()->sole();

        app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '6'], 'idem-over-a');

        $this->expectException(OverReceiptException::class);

        try {
            app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '5'], 'idem-over-b');
        } finally {
            self::assertSame('6.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
            self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        }
    }

    public function test_retry_receipt_with_the_same_idempotency_key_never_doubles_stock(): void
    {
        [$order, $actor, $product] = $this->readyOrderedOrder(withProductReturn: true, orderedQuantity: '10');
        $line = $order->fresh()->lines()->sole();

        $first = app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '6'], 'idem-retry-1');
        $second = app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '6'], 'idem-retry-1');

        self::assertSame($first->purchaseReceipt->id, $second->purchaseReceipt->id);
        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        self::assertSame('6.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
    }

    public function test_the_same_idempotency_key_reused_on_a_different_order_in_the_same_context_is_refused(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, [CommercialPermission::MANAGE_PURCHASES, CommercialPermission::RECEIVE_PURCHASES]);
        $supplier = $this->supplier($context);

        $productA = $this->product($context, ['name' => 'Produit A']);
        $orderA = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($orderA, $productA, '5', 1000);
        $confirmedA = app(ConfirmPurchaseOrder::class)->handle($orderA, $actor, 'idem-order-a')->purchaseOrder;
        $lineA = $confirmedA->lines()->sole();

        $productB = $this->product($context, ['name' => 'Produit B']);
        $orderB = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($orderB, $productB, '5', 1000);
        $confirmedB = app(ConfirmPurchaseOrder::class)->handle($orderB, $actor, 'idem-order-b')->purchaseOrder;
        $lineB = $confirmedB->lines()->sole();

        $sharedKey = 'idem-shared-key';
        app(ReceivePurchaseOrder::class)->handle($confirmedA, $actor, [$lineA->id => '5'], $sharedKey);

        $this->expectException(HttpException::class);

        try {
            app(ReceivePurchaseOrder::class)->handle($confirmedB->fresh(), $actor, [$lineB->id => '5'], $sharedKey);
        } finally {
            self::assertSame(0, StockMovement::query()->where('product_id', $productB->id)->count());
            self::assertSame(PurchaseOrder::STATUS_ORDERED, $confirmedB->fresh()->status);
        }
    }

    public function test_the_same_idempotency_key_reused_across_contexts_is_refused_and_returns_nothing_foreign(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();

        $actorA = $this->actor($contextA, [CommercialPermission::MANAGE_PURCHASES, CommercialPermission::RECEIVE_PURCHASES]);
        $supplierA = $this->supplier($contextA);
        $productA = $this->product($contextA);
        $orderA = app(PurchaseOrderDraftService::class)->createDraft($contextA, $actorA->identity, $supplierA);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($orderA, $productA, '5', 1000);
        $confirmedA = app(ConfirmPurchaseOrder::class)->handle($orderA, $actorA, 'idem-context-a')->purchaseOrder;
        $lineA = $confirmedA->lines()->sole();

        $actorB = $this->actor($contextB, [CommercialPermission::MANAGE_PURCHASES, CommercialPermission::RECEIVE_PURCHASES]);
        $supplierB = $this->supplier($contextB);
        $productB = $this->product($contextB);
        $orderB = app(PurchaseOrderDraftService::class)->createDraft($contextB, $actorB->identity, $supplierB);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($orderB, $productB, '5', 1000);
        $confirmedB = app(ConfirmPurchaseOrder::class)->handle($orderB, $actorB, 'idem-context-b')->purchaseOrder;
        $lineB = $confirmedB->lines()->sole();

        $sharedKey = 'idem-cross-context-key';
        $resultA = app(ReceivePurchaseOrder::class)->handle($confirmedA, $actorA, [$lineA->id => '5'], $sharedKey);

        $caught = null;

        try {
            app(ReceivePurchaseOrder::class)->handle($confirmedB->fresh(), $actorB, [$lineB->id => '5'], $sharedKey);
        } catch (HttpException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'La réutilisation de la clé à travers deux contextes doit être refusée.');
        self::assertSame(0, StockMovement::query()->where('product_id', $productB->id)->count());
        self::assertSame(PurchaseOrder::STATUS_ORDERED, $confirmedB->fresh()->status);
        // Le contexte A n'a lui-même jamais été affecté par la tentative refusée sur B.
        self::assertSame($resultA->purchaseReceipt->id, $confirmedA->receipts()->sole()->id);
        self::assertSame(1, $confirmedA->receipts()->count());
    }

    public function test_two_concurrent_receipts_never_exceed_the_ordered_quantity(): void
    {
        [$order, $actor, $product] = $this->readyOrderedOrder(withProductReturn: true, orderedQuantity: '10');
        $line = $order->fresh()->lines()->sole();

        // Simule deux réceptions « concurrentes » en série (le verrou de ligne empêche de toute
        // façon un entrelacement réel) : la seconde tentative qui dépasse le commandé échoue.
        app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '7'], 'idem-concurrent-a');

        $blocked = false;
        try {
            app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '7'], 'idem-concurrent-b');
        } catch (OverReceiptException) {
            $blocked = true;
        }

        self::assertTrue($blocked);
        self::assertSame('7.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
    }

    public function test_an_untracked_service_never_creates_a_stock_movement_on_receipt(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, [CommercialPermission::MANAGE_PURCHASES, CommercialPermission::RECEIVE_PURCHASES]);
        $supplier = $this->supplier($context);
        $service = $this->product($context, ['kind' => Product::KIND_SERVICE, 'track_stock' => false]);

        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $service, '3', 5000);
        $confirmed = app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-service-1')->purchaseOrder;
        $line = $confirmed->lines()->sole();

        app(ReceivePurchaseOrder::class)->handle($confirmed, $actor, [$line->id => '3'], 'idem-service-receive-1');

        self::assertSame(0, StockMovement::query()->where('product_id', $service->id)->count());
    }

    public function test_payment_before_full_receipt_is_refused(): void
    {
        [$order, $actor] = $this->readyOrderedOrder();

        $this->expectException(HttpException::class);
        app(RecordCashPurchasePayment::class)->handle($order->fresh(), $actor, 'idem-early-pay-1');
    }

    public function test_payment_after_full_receipt_is_exact_and_unique(): void
    {
        [$order, $actor, $product] = $this->readyOrderedOrder(withProductReturn: true, orderedQuantity: '3', unitCostXof: 2000);
        $line = $order->fresh()->lines()->sole();
        $received = app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '3'], 'idem-full-receive-1');
        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $received->purchaseOrder->status);

        $payment = app(RecordCashPurchasePayment::class)->handle($received->purchaseOrder, $actor, 'idem-pay-1');

        self::assertSame(6000, $payment->amount_xof);
        self::assertSame(1, Payment::query()->where('purchase_order_id', $order->id)->count());
    }

    public function test_retry_payment_does_not_double_the_write(): void
    {
        [$order, $actor] = $this->readyOrderedOrder(orderedQuantity: '2', unitCostXof: 1500);
        $line = $order->fresh()->lines()->sole();
        $received = app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '2'], 'idem-retry-receive-1');

        $first = app(RecordCashPurchasePayment::class)->handle($received->purchaseOrder, $actor, 'idem-retry-pay-1');
        $second = app(RecordCashPurchasePayment::class)->handle($received->purchaseOrder->fresh(), $actor, 'idem-retry-pay-2');

        self::assertSame($first->id, $second->id);
        self::assertSame(1, Payment::query()->where('purchase_order_id', $order->id)->count());
    }

    public function test_documents_keep_their_snapshot_after_supplier_and_product_change(): void
    {
        [$order, $actor] = $this->readyDraftOrder();
        $result = app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-snapshot-1');
        $originalSupplierName = $result->document->snapshot['supplier_display_name'];
        $originalLineName = $result->document->snapshot['lines'][0]['product_name'];

        Supplier::query()->whereKey($order->supplier_id)->update(['display_name' => 'Nom modifié']);
        $result->purchaseOrder->lines()->first()->product->update(['name' => 'Produit modifié']);

        self::assertSame($originalSupplierName, $result->document->fresh()->snapshot['supplier_display_name']);
        self::assertSame($originalLineName, $result->document->fresh()->snapshot['lines'][0]['product_name']);
    }

    public function test_an_order_with_a_receipt_cannot_be_cancelled(): void
    {
        [$order, $actor] = $this->readyOrderedOrder(orderedQuantity: '5');
        $line = $order->fresh()->lines()->sole();
        app(ReceivePurchaseOrder::class)->handle($order->fresh(), $actor, [$line->id => '1'], 'idem-cancel-guard-1');

        $this->expectException(PurchaseOrderNotCancellableException::class);
        app(CancelPurchaseOrder::class)->handle($order->fresh(), $actor);
    }

    public function test_a_suspended_context_refuses_confirmation_receipt_and_payment(): void
    {
        [$order, $actor] = $this->readyDraftOrder();
        CommercialContext::query()->whereKey($order->context_id)->update(['status' => CommercialContext::STATUS_SUSPENDED]);

        $this->expectException(CommercialContextSuspendedException::class);
        app(ConfirmPurchaseOrder::class)->handle($order->fresh(), $actor, 'idem-suspended-1');
    }

    public function test_audit_events_carry_actor_context_and_aggregate(): void
    {
        [$order, $actor] = $this->readyDraftOrder();
        app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-audit-1');

        $event = CommercialAuditEvent::query()->where('event_type', 'purchase.ordered')->sole();
        self::assertSame($actor->identity->reference, $event->actor_core_reference);
        self::assertSame($order->context_id, $event->context_id);
        self::assertSame((string) $order->id, $event->aggregate_reference);
    }

    public function test_reorder_threshold_is_contextual_and_creates_no_automatic_order(): void
    {
        $context = $this->context();
        $product = $this->product($context, ['reorder_threshold' => '5.000']);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => '3.000']);

        self::assertLessThanOrEqual(
            0,
            Quantity::compare((string) $product->stockBalance->quantity, (string) $product->reorder_threshold),
        );
        self::assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_sales_still_work_without_regression(): void
    {
        $context = $this->context();
        $product = $this->product($context, ['sale_price_xof' => 1500]);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => 10]);
        $actor = $this->actor($context, [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);
        $this->openCashSession($context, $actor);

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product, '2');
        $result = app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-regression-1');

        self::assertSame(3000, $result->sale->total_xof);
        self::assertSame('8.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
    }

    /**
     * @return array{0: PurchaseOrder, 1: CurrentActor, 2?: Product}
     */
    private function readyDraftOrder(bool $withProductReturn = false, string $quantity = '3', int $unitCostXof = 1000): array
    {
        $context = $this->context();
        $actor = $this->actor($context, [CommercialPermission::MANAGE_PURCHASES, CommercialPermission::RECEIVE_PURCHASES, CommercialPermission::PAY_PURCHASES, CommercialPermission::OPERATE_CASH]);
        $this->openCashSession($context, $actor, 1_000_000);
        $supplier = $this->supplier($context);
        $product = $this->product($context);

        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, $quantity, $unitCostXof);

        return $withProductReturn ? [$order->fresh(), $actor, $product] : [$order->fresh(), $actor];
    }

    /**
     * Ouvre une vraie session de caisse pour l'acteur (docs/implementation/LOT-003-CASH-REGISTER-
     * CLOSING.md §36 : interdiction explicite de tout bypass de test pour la caisse).
     */
    private function openCashSession(CommercialContext $context, CurrentActor $actor, int $openingAmountXof = 0): CashSession
    {
        $register = CashRegister::query()->create([
            'context_id' => $context->id,
            'name' => 'Caisse de test',
            'status' => CashRegister::STATUS_ACTIVE,
            'created_by_core_reference' => $actor->identity->reference,
        ]);

        return app(OpenCashSession::class)->handle($register, $actor, $openingAmountXof, (string) Str::uuid());
    }

    /**
     * @return array{0: PurchaseOrder, 1: CurrentActor, 2?: Product}
     */
    private function readyOrderedOrder(bool $withProductReturn = false, string $orderedQuantity = '3', int $unitCostXof = 1000): array
    {
        [$order, $actor, $product] = $this->readyDraftOrder(true, $orderedQuantity, $unitCostXof);
        $confirmed = app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-setup-'.$order->id)->purchaseOrder;

        return $withProductReturn ? [$confirmed, $actor, $product] : [$confirmed, $actor];
    }

    private function context(array $overrides = []): CommercialContext
    {
        return CommercialContext::query()->create(array_replace([
            'display_name' => 'Boutique de test',
            'currency' => 'XOF',
            'timezone' => 'Africa/Abidjan',
            'status' => CommercialContext::STATUS_ACTIVE,
        ], $overrides));
    }

    private function supplier(CommercialContext $context, array $overrides = []): Supplier
    {
        return app(SupplierManager::class)->create($context, new CoreIdentityReference('IDN-SUPPLIER-SEED'), array_replace([
            'display_name' => 'Fournisseur de test',
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

    private function actor(CommercialContext $context, array $permissions, ?string $reference = null): CurrentActor
    {
        $reference ??= 'IDN-TEST-'.CommercialContextMember::query()->count();
        $identity = new CoreIdentityReference($reference, 'Acteur de test');

        $member = CommercialContextMember::query()->firstOrCreate(
            ['context_id' => $context->id, 'core_identity_reference' => $reference],
            ['permissions' => $permissions, 'status' => CommercialContextMember::STATUS_ACTIVE],
        );

        return (new CurrentActor($identity))->withActiveContext($context, $member->permissions);
    }
}
