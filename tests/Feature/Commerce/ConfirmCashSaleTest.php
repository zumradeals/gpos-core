<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Application\Commerce\ConfirmCashSale;
use App\Application\Commerce\OpenCashSession;
use App\Application\Commerce\ProductCatalog;
use App\Application\Commerce\SaleDraftService;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\InsufficientStockException;
use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CurrentActor;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CommercialAuditEvent;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Couvre les tests d'acceptation métier de docs/implementation/LOT-001-APP-SHELL-COMMERCE-
 * SLICE.md §22 : isolation, permissions, montants entiers, calcul serveur, confirmation
 * transactionnelle, idempotence, stock insuffisant, snapshots, audit, contexte suspendu.
 */
final class ConfirmCashSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_isolated_by_commercial_context(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $productA = $this->product($contextA, ['name' => 'Produit A']);
        $this->product($contextB, ['name' => 'Produit B']);

        $visible = Product::query()->where('context_id', $contextA->id)->pluck('name')->all();

        self::assertSame(['Produit A'], $visible);
        self::assertSame('Produit A', $productA->fresh()->name);
    }

    public function test_an_actor_without_sell_permission_cannot_confirm_a_sale(): void
    {
        $context = $this->context();
        $product = $this->product($context);
        $actor = $this->actor($context, []); // aucune permission

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product);

        $this->expectException(HttpException::class);
        app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-1');
    }

    public function test_product_creation_respects_the_commercial_context(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, [CommercialPermission::MANAGE_CATALOG]);

        $product = app(ProductCatalog::class)->create($context, $actor->identity, [
            'name' => 'Sac de riz 25kg', 'sale_price_xof' => 15000, 'kind' => Product::KIND_PRODUCT,
        ]);

        self::assertSame($context->id, $product->context_id);
        self::assertTrue(StockBalance::query()->where('product_id', $product->id)->exists());
    }

    public function test_monetary_price_is_a_strict_integer(): void
    {
        $context = $this->context();
        $actor = $this->actor($context, [CommercialPermission::MANAGE_CATALOG]);

        $product = app(ProductCatalog::class)->create($context, $actor->identity, [
            'name' => 'Savon local', 'sale_price_xof' => 500, 'kind' => Product::KIND_PRODUCT,
        ]);

        self::assertIsInt($product->sale_price_xof);
        self::assertSame(500, $product->sale_price_xof);
    }

    public function test_draft_sale_lines_compute_totals_server_side(): void
    {
        $context = $this->context();
        $product = $this->product($context, ['sale_price_xof' => 1200]);
        $actor = $this->actor($context, [CommercialPermission::SELL]);

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product, '3');

        $sale->refresh();
        self::assertSame(3600, $sale->subtotal_xof);
        self::assertSame(3600, $sale->total_xof);
    }

    public function test_cash_confirmation_creates_exactly_one_confirmed_sale(): void
    {
        [$sale, $actor] = $this->readyDraftSale();

        app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-confirm-1');

        self::assertSame(1, Sale::query()->where('status', Sale::STATUS_CONFIRMED)->count());
    }

    public function test_a_payment_is_created_exactly_once(): void
    {
        [$sale, $actor] = $this->readyDraftSale();

        app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-pay-1');

        self::assertSame(1, Payment::query()->where('sale_id', $sale->id)->count());
    }

    public function test_stock_is_decremented_exactly_once(): void
    {
        [$sale, $actor, $product] = $this->readyDraftSale(withProductReturn: true);
        $this->setBalance($product, '10');

        app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-stock-1');

        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        self::assertSame('8.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
    }

    public function test_retry_with_the_same_idempotency_key_does_not_move_stock_twice(): void
    {
        [$sale, $actor, $product] = $this->readyDraftSale(withProductReturn: true);
        $this->setBalance($product, '10');

        $first = app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-retry-1');
        $second = app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-retry-1');

        self::assertSame($first->payment->id, $second->payment->id);
        self::assertSame($first->document->id, $second->document->id);
        self::assertSame(1, StockMovement::query()->where('product_id', $product->id)->count());
        self::assertSame(1, Payment::query()->where('sale_id', $sale->id)->count());
    }

    public function test_insufficient_stock_refuses_the_whole_transaction_atomically(): void
    {
        [$sale, $actor, $product] = $this->readyDraftSale(withProductReturn: true, quantity: '5');
        $this->setBalance($product, '2');

        $this->expectException(InsufficientStockException::class);

        try {
            app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-insufficient-1');
        } finally {
            self::assertSame(Sale::STATUS_DRAFT, $sale->fresh()->status);
            self::assertSame(0, Payment::query()->where('sale_id', $sale->id)->count());
            self::assertSame(0, StockMovement::query()->where('product_id', $product->id)->count());
        }
    }

    public function test_an_untracked_service_never_creates_a_stock_movement(): void
    {
        $context = $this->context();
        $service = $this->product($context, ['kind' => Product::KIND_SERVICE, 'track_stock' => false, 'sale_price_xof' => 2000]);
        $actor = $this->actor($context, [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);
        $this->openCashSession($context, $actor);

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $service);

        app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-service-1');

        self::assertSame(0, StockMovement::query()->where('product_id', $service->id)->count());
    }

    public function test_the_receipt_keeps_its_snapshot_even_after_the_product_changes(): void
    {
        [$sale, $actor, $product] = $this->readyDraftSale(withProductReturn: true);
        $this->setBalance($product, '10');

        $result = app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-snapshot-1');
        $originalName = $result->document->snapshot['lines'][0]['product_name'];

        $product->update(['name' => 'Nom modifié après coup', 'sale_price_xof' => 999999]);

        self::assertSame($originalName, $result->document->fresh()->snapshot['lines'][0]['product_name']);
        self::assertNotSame(999999, $result->document->fresh()->snapshot['lines'][0]['unit_price_xof']);
    }

    public function test_audit_events_carry_actor_context_and_aggregate(): void
    {
        [$sale, $actor] = $this->readyDraftSale();

        app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-audit-1');

        $event = CommercialAuditEvent::query()->where('event_type', 'sale.confirmed')->sole();
        self::assertSame($actor->identity->reference, $event->actor_core_reference);
        self::assertSame($sale->context_id, $event->context_id);
        self::assertSame((string) $sale->id, $event->aggregate_reference);
    }

    public function test_a_suspended_context_refuses_the_mutation(): void
    {
        [$sale, $actor] = $this->readyDraftSale();
        CommercialContext::query()->whereKey($sale->context_id)->update(['status' => CommercialContext::STATUS_SUSPENDED]);

        $this->expectException(CommercialContextSuspendedException::class);
        app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-suspended-1');
    }

    /**
     * @return array{0: Sale, 1: CurrentActor, 2?: Product}
     */
    private function readyDraftSale(bool $withProductReturn = false, string $quantity = '2'): array
    {
        $context = $this->context();
        $product = $this->product($context, ['sale_price_xof' => 1000]);
        $this->setBalance($product, '100');
        $actor = $this->actor($context, [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);
        $this->openCashSession($context, $actor);

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product, $quantity);

        return $withProductReturn ? [$sale->fresh(), $actor, $product] : [$sale->fresh(), $actor];
    }

    /**
     * Ouvre une vraie session de caisse pour l'acteur (docs/implementation/LOT-003-CASH-REGISTER-
     * CLOSING.md §36 : interdiction explicite de tout bypass de test pour la caisse).
     */
    private function openCashSession(CommercialContext $context, CurrentActor $actor): CashSession
    {
        $register = CashRegister::query()->create([
            'context_id' => $context->id,
            'name' => 'Caisse de test',
            'status' => CashRegister::STATUS_ACTIVE,
            'created_by_core_reference' => $actor->identity->reference,
        ]);

        return app(OpenCashSession::class)->handle($register, $actor, 0, (string) Str::uuid());
    }

    private function setBalance(Product $product, string $quantity): void
    {
        StockBalance::query()->updateOrCreate(
            ['context_id' => $product->context_id, 'product_id' => $product->id],
            ['quantity' => $quantity],
        );
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

    private function actor(CommercialContext $context, array $permissions): CurrentActor
    {
        $reference = 'IDN-TEST-'.CommercialContextMember::query()->count();
        $identity = new CoreIdentityReference($reference, 'Acteur de test');

        $member = CommercialContextMember::query()->create([
            'context_id' => $context->id,
            'core_identity_reference' => $reference,
            'permissions' => $permissions,
            'status' => CommercialContextMember::STATUS_ACTIVE,
        ]);

        return (new CurrentActor($identity))->withActiveContext($context, $member->permissions);
    }
}
