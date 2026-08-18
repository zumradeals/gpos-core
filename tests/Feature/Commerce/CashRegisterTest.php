<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Application\Commerce\CashBalanceCalculator;
use App\Application\Commerce\CashSessionResolver;
use App\Application\Commerce\CloseCashSession;
use App\Application\Commerce\ConfirmCashSale;
use App\Application\Commerce\ConfirmPurchaseOrder;
use App\Application\Commerce\OpenCashSession;
use App\Application\Commerce\PurchaseOrderDraftService;
use App\Application\Commerce\ReceivePurchaseOrder;
use App\Application\Commerce\RecordCashPurchasePayment;
use App\Application\Commerce\RecordManualCashMovement;
use App\Application\Commerce\SaleDraftService;
use App\Application\Commerce\SupplierManager;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CashSessionNotCloseableException;
use App\Domain\Commerce\Exceptions\CashSessionNotOpenableException;
use App\Domain\Commerce\Exceptions\CashVarianceReasonRequiredException;
use App\Domain\Commerce\Exceptions\InsufficientCashBalanceException;
use App\Domain\Commerce\Exceptions\InvalidManualCashMovementException;
use App\Domain\Commerce\Exceptions\NoOpenCashSessionException;
use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CurrentActor;
use App\Infrastructure\Identity\DevCoreSessionGateway;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CommercialAuditEvent;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use App\Models\CommercialDocument;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Couvre les tests d'acceptation métier de docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md
 * §35 : caisse, session, mouvements, solde attendu, clôture, idempotence, concurrence, audit,
 * régression LOT-001/LOT-002.
 */
final class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cash_register_belongs_to_a_single_context(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $registerA = $this->register($contextA, ['name' => 'Caisse A']);
        $this->register($contextB, ['name' => 'Caisse B']);

        $visible = CashRegister::query()->where('context_id', $contextA->id)->pluck('name')->all();

        self::assertSame(['Caisse A'], $visible);
        self::assertSame($contextA->id, $registerA->fresh()->context_id);
    }

    public function test_an_actor_without_manage_cash_cannot_create_a_register(): void
    {
        $context = $this->context();
        $this->member($context, 'IDN-NO-MANAGE-CASH', [CommercialPermission::VIEW_CASH]);

        $this->withSession([DevCoreSessionGateway::SESSION_KEY => 'IDN-NO-MANAGE-CASH'])
            ->post('/caisse/registres', ['name' => 'Caisse illégitime'])
            ->assertForbidden();

        self::assertSame(0, CashRegister::query()->where('context_id', $context->id)->count());
    }

    public function test_an_actor_without_operate_cash_cannot_open_a_session(): void
    {
        $context = $this->context();
        $register = $this->register($context);
        $actor = $this->actor($context, []); // aucune permission

        $this->expectException(HttpException::class);
        app(OpenCashSession::class)->handle($register, $actor, 0, (string) Str::uuid());
    }

    public function test_opening_with_zero_funds_works(): void
    {
        $context = $this->context();
        $register = $this->register($context);
        $actor = $this->actor($context, [CommercialPermission::OPERATE_CASH]);

        $session = app(OpenCashSession::class)->handle($register, $actor, 0, (string) Str::uuid());

        self::assertSame(CashSession::STATUS_OPEN, $session->status);
        self::assertSame(0, $session->opening_amount_xof);
        self::assertSame(0, CashMovement::query()->where('cash_session_id', $session->id)->count());
        self::assertSame(0, app(CashBalanceCalculator::class)->expected($session));
    }

    public function test_opening_with_10000_creates_an_expected_of_10000(): void
    {
        $context = $this->context();
        $register = $this->register($context);
        $actor = $this->actor($context, [CommercialPermission::OPERATE_CASH]);

        $session = app(OpenCashSession::class)->handle($register, $actor, 10000, (string) Str::uuid());

        self::assertSame(1, CashMovement::query()->where('cash_session_id', $session->id)->where('movement_type', CashMovement::TYPE_OPENING_FLOAT)->count());
        self::assertSame(10000, app(CashBalanceCalculator::class)->expected($session));
    }

    public function test_invalid_or_negative_opening_amount_is_refused(): void
    {
        $context = $this->context();
        $register = $this->register($context);
        $actor = $this->actor($context, [CommercialPermission::OPERATE_CASH]);

        $this->expectException(CashSessionNotOpenableException::class);
        app(OpenCashSession::class)->handle($register, $actor, -1, (string) Str::uuid());
    }

    public function test_two_open_sessions_on_the_same_register_are_impossible(): void
    {
        $context = $this->context();
        $register = $this->register($context);
        $actorA = $this->actor($context, [CommercialPermission::OPERATE_CASH], 'IDN-REG-A');
        $actorB = $this->actor($context, [CommercialPermission::OPERATE_CASH], 'IDN-REG-B');

        app(OpenCashSession::class)->handle($register, $actorA, 0, (string) Str::uuid());

        $this->expectException(CashSessionNotOpenableException::class);
        app(OpenCashSession::class)->handle($register->fresh(), $actorB, 0, (string) Str::uuid());
    }

    public function test_an_actor_cannot_have_two_open_sessions_in_the_same_context(): void
    {
        $context = $this->context();
        $registerA = $this->register($context, ['name' => 'Caisse A']);
        $registerB = $this->register($context, ['name' => 'Caisse B']);
        $actor = $this->actor($context, [CommercialPermission::OPERATE_CASH]);

        app(OpenCashSession::class)->handle($registerA, $actor, 0, (string) Str::uuid());

        $this->expectException(CashSessionNotOpenableException::class);
        app(OpenCashSession::class)->handle($registerB, $actor, 0, (string) Str::uuid());
    }

    public function test_a_foreign_or_cross_context_session_is_refused(): void
    {
        $contextA = $this->context();
        $contextB = $this->context();
        $identity = new CoreIdentityReference('IDN-CROSS-CASH');
        $memberA = CommercialContextMember::query()->create([
            'context_id' => $contextA->id, 'core_identity_reference' => 'IDN-CROSS-CASH',
            'permissions' => [CommercialPermission::OPERATE_CASH], 'status' => CommercialContextMember::STATUS_ACTIVE,
        ]);
        CommercialContextMember::query()->create([
            'context_id' => $contextB->id, 'core_identity_reference' => 'IDN-CROSS-CASH',
            'permissions' => [CommercialPermission::OPERATE_CASH], 'status' => CommercialContextMember::STATUS_ACTIVE,
        ]);
        $actorA = (new CurrentActor($identity))->withActiveContext($contextA, $memberA->permissions);

        app(OpenCashSession::class)->handle($this->register($contextA), $actorA, 0, (string) Str::uuid());

        $this->expectException(NoOpenCashSessionException::class);
        app(CashSessionResolver::class)->requireOpenSessionForActor($contextB, $identity);
    }

    public function test_cash_sale_without_open_session_is_refused_atomically(): void
    {
        $context = $this->context();
        $product = $this->product($context, ['sale_price_xof' => 1000]);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => 10]);
        $actor = $this->actor($context, [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product, '2');

        $this->expectException(NoOpenCashSessionException::class);

        try {
            app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-no-session-1');
        } finally {
            self::assertSame(Sale::STATUS_DRAFT, $sale->fresh()->status);
            self::assertSame(0, Payment::query()->where('sale_id', $sale->id)->count());
        }
    }

    public function test_cash_sale_with_open_session_creates_exactly_one_in_movement(): void
    {
        [$sale, $actor] = $this->readySaleWithOpenSession();

        $result = app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-in-movement-1');

        $movement = CashMovement::query()->where('payment_id', $result->payment->id)->sole();
        self::assertSame(CashMovement::TYPE_SALE_PAYMENT, $movement->movement_type);
        self::assertSame(CashMovement::DIRECTION_IN, $movement->direction);
        self::assertSame($result->payment->amount_xof, $movement->amount_xof);
    }

    public function test_retry_cash_sale_never_doubles_the_movement(): void
    {
        [$sale, $actor] = $this->readySaleWithOpenSession();

        app(ConfirmCashSale::class)->handle($sale, $actor, 'idem-retry-sale-1');
        app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-retry-sale-1');

        self::assertSame(1, CashMovement::query()->where('movement_type', CashMovement::TYPE_SALE_PAYMENT)->count());
    }

    public function test_cash_purchase_without_open_session_is_refused_atomically(): void
    {
        [$order, $actor] = $this->readyReceivedPurchaseOrder(withOpenSession: false);

        $this->expectException(NoOpenCashSessionException::class);

        try {
            app(RecordCashPurchasePayment::class)->handle($order->fresh(), $actor, 'idem-purchase-no-session-1');
        } finally {
            self::assertSame(0, Payment::query()->where('purchase_order_id', $order->id)->count());
        }
    }

    public function test_cash_purchase_with_open_session_creates_exactly_one_out_movement(): void
    {
        [$order, $actor] = $this->readyReceivedPurchaseOrder(unitCostXof: 2000, quantity: '3', openingAmountXof: 100000);

        $payment = app(RecordCashPurchasePayment::class)->handle($order->fresh(), $actor, 'idem-out-movement-1');

        $movement = CashMovement::query()->where('payment_id', $payment->id)->sole();
        self::assertSame(CashMovement::TYPE_PURCHASE_PAYMENT, $movement->movement_type);
        self::assertSame(CashMovement::DIRECTION_OUT, $movement->direction);
        self::assertSame(6000, $movement->amount_xof);
    }

    public function test_cash_purchase_exceeding_available_is_refused_and_payment_not_created(): void
    {
        [$order, $actor] = $this->readyReceivedPurchaseOrder(unitCostXof: 5000, quantity: '2', openingAmountXof: 1000);

        $this->expectException(InsufficientCashBalanceException::class);

        try {
            app(RecordCashPurchasePayment::class)->handle($order->fresh(), $actor, 'idem-insufficient-1');
        } finally {
            self::assertSame(0, Payment::query()->where('purchase_order_id', $order->id)->count());
        }
    }

    public function test_manual_in_movement_requires_reason_and_valid_amount(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH]);

        try {
            app(RecordManualCashMovement::class)->handle($session, $actor, CashMovement::DIRECTION_IN, 0, 'motif', (string) Str::uuid());
            self::fail('Un montant nul aurait dû être refusé.');
        } catch (InvalidManualCashMovementException) {
        }

        try {
            app(RecordManualCashMovement::class)->handle($session, $actor, CashMovement::DIRECTION_IN, 500, '  ', (string) Str::uuid());
            self::fail('Un motif vide aurait dû être refusé.');
        } catch (InvalidManualCashMovementException) {
        }

        $movement = app(RecordManualCashMovement::class)->handle($session, $actor, CashMovement::DIRECTION_IN, 500, 'Appoint de caisse', (string) Str::uuid());
        self::assertSame(CashMovement::TYPE_MANUAL_IN, $movement->movement_type);
        self::assertSame(CashMovement::DIRECTION_IN, $movement->direction);
    }

    public function test_manual_out_movement_requires_reason_and_sufficient_balance(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH], openingAmountXof: 1000);

        try {
            app(RecordManualCashMovement::class)->handle($session, $actor, CashMovement::DIRECTION_OUT, 5000, 'Trop', (string) Str::uuid());
            self::fail('Une sortie supérieure au solde aurait dû être refusée.');
        } catch (InsufficientCashBalanceException) {
        }

        $movement = app(RecordManualCashMovement::class)->handle($session, $actor, CashMovement::DIRECTION_OUT, 400, 'Achat de sacs', (string) Str::uuid());
        self::assertSame(CashMovement::TYPE_MANUAL_OUT, $movement->movement_type);
        self::assertSame(600, app(CashBalanceCalculator::class)->expected($session->fresh()));
    }

    public function test_manual_movement_retry_never_doubles(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH]);

        app(RecordManualCashMovement::class)->handle($session, $actor, CashMovement::DIRECTION_IN, 700, 'Appoint', 'idem-manual-retry-1');
        app(RecordManualCashMovement::class)->handle($session->fresh(), $actor, CashMovement::DIRECTION_IN, 700, 'Appoint', 'idem-manual-retry-1');

        self::assertSame(1, CashMovement::query()->where('idempotency_key', 'idem-manual-retry-1')->count());
    }

    public function test_expected_equals_opening_plus_in_minus_out(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH], openingAmountXof: 5000);

        app(RecordManualCashMovement::class)->handle($session, $actor, CashMovement::DIRECTION_IN, 2000, 'Entrée', (string) Str::uuid());
        app(RecordManualCashMovement::class)->handle($session->fresh(), $actor, CashMovement::DIRECTION_OUT, 1000, 'Sortie', (string) Str::uuid());

        self::assertSame(6000, app(CashBalanceCalculator::class)->expected($session->fresh()));
    }

    public function test_movements_from_another_session_or_context_never_affect_expected(): void
    {
        [$sessionA, $actorA] = $this->openSessionFor([CommercialPermission::OPERATE_CASH], openingAmountXof: 1000, reference: 'IDN-EXPECTED-A');
        [$sessionB, $actorB] = $this->openSessionFor([CommercialPermission::OPERATE_CASH], openingAmountXof: 9000, reference: 'IDN-EXPECTED-B');

        app(RecordManualCashMovement::class)->handle($sessionB, $actorB, CashMovement::DIRECTION_IN, 5000, 'Entrée B', (string) Str::uuid());

        self::assertSame(1000, app(CashBalanceCalculator::class)->expected($sessionA->fresh()));
    }

    public function test_a_balanced_closure_produces_closed(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH], openingAmountXof: 3000);

        $result = app(CloseCashSession::class)->handle($session->fresh(), $actor, 3000, null, (string) Str::uuid());

        self::assertSame(CashSession::STATUS_CLOSED, $result->cashSession->status);
        self::assertSame(0, $result->cashSession->variance_xof);
        self::assertSame(CommercialDocument::TYPE_CASH_CLOSURE, $result->document->document_type);
    }

    public function test_a_closure_with_variance_produces_closed_with_variance(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH], openingAmountXof: 3000);

        $result = app(CloseCashSession::class)->handle($session->fresh(), $actor, 2500, 'Erreur de comptage', (string) Str::uuid());

        self::assertSame(CashSession::STATUS_CLOSED_WITH_VARIANCE, $result->cashSession->status);
        self::assertSame(-500, $result->cashSession->variance_xof);
    }

    public function test_a_variance_without_reason_is_refused(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH], openingAmountXof: 3000);

        $this->expectException(CashVarianceReasonRequiredException::class);

        try {
            app(CloseCashSession::class)->handle($session->fresh(), $actor, 2500, null, (string) Str::uuid());
        } finally {
            self::assertSame(CashSession::STATUS_OPEN, $session->fresh()->status);
        }
    }

    public function test_a_positive_variance_is_recorded_correctly(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH], openingAmountXof: 3000);

        $result = app(CloseCashSession::class)->handle($session->fresh(), $actor, 3200, 'Pourboire non enregistré', (string) Str::uuid());

        self::assertSame(200, $result->cashSession->variance_xof);
        self::assertGreaterThan(0, $result->cashSession->variance_xof);
    }

    public function test_a_negative_variance_is_recorded_correctly(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH], openingAmountXof: 3000);

        $result = app(CloseCashSession::class)->handle($session->fresh(), $actor, 2900, 'Manque constaté', (string) Str::uuid());

        self::assertSame(-100, $result->cashSession->variance_xof);
        self::assertLessThan(0, $result->cashSession->variance_xof);
    }

    public function test_a_closed_session_accepts_no_new_movement(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);
        app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, (string) Str::uuid());

        $this->expectException(HttpException::class);
        app(RecordManualCashMovement::class)->handle($session->fresh(), $actor, CashMovement::DIRECTION_IN, 500, 'Trop tard', (string) Str::uuid());
    }

    public function test_a_concurrent_payment_loses_to_closure_and_writes_nothing(): void
    {
        $context = $this->context();
        $product = $this->product($context, ['sale_price_xof' => 1000]);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => 10]);
        $actor = $this->actor($context, [
            CommercialPermission::SELL, CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH,
        ]);
        $register = $this->register($context);
        $session = app(OpenCashSession::class)->handle($register, $actor, 0, (string) Str::uuid());

        // La caisse est clôturée en premier (simule la clôture qui gagne la course, §16). Un
        // verrou de session réel empêche l'entrelacement ; ce test vérifie l'issue, pas le
        // scheduling, comme le fait déjà PurchasingTest pour les réceptions concurrentes.
        app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, (string) Str::uuid());

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product, '2');

        $this->expectException(NoOpenCashSessionException::class);

        try {
            app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-concurrent-close-1');
        } finally {
            self::assertSame(Sale::STATUS_DRAFT, $sale->fresh()->status);
            self::assertSame(0, CashMovement::query()->where('cash_session_id', $session->id)->where('movement_type', CashMovement::TYPE_SALE_PAYMENT)->count());
        }
    }

    public function test_closure_retry_with_the_same_key_returns_the_same_proof(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);

        $first = app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, 'idem-close-retry-1');
        $second = app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, 'idem-close-retry-1');

        self::assertSame($first->document->id, $second->document->id);
        self::assertSame(1, CommercialDocument::query()->where('cash_session_id', $session->id)->count());
    }

    public function test_the_same_closure_key_on_a_different_session_or_context_is_refused(): void
    {
        [$sessionA, $actorA] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH], reference: 'IDN-CLOSE-KEY-A');
        [$sessionB, $actorB] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH], reference: 'IDN-CLOSE-KEY-B');

        $sharedKey = 'idem-close-shared-key';
        app(CloseCashSession::class)->handle($sessionA->fresh(), $actorA, 0, null, $sharedKey);

        $this->expectException(HttpException::class);

        try {
            app(CloseCashSession::class)->handle($sessionB->fresh(), $actorB, 0, null, $sharedKey);
        } finally {
            self::assertSame(CashSession::STATUS_OPEN, $sessionB->fresh()->status);
        }
    }

    public function test_a_session_already_closed_with_a_different_key_is_an_explicit_conflict(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);
        app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, 'idem-first-close-key');

        $this->expectException(CashSessionNotCloseableException::class);
        app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, 'idem-second-different-key');
    }

    public function test_exactly_one_cash_closure_document_per_session(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);

        app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, (string) Str::uuid());

        self::assertSame(1, CommercialDocument::query()->where('cash_session_id', $session->id)->where('document_type', CommercialDocument::TYPE_CASH_CLOSURE)->count());
    }

    public function test_the_closure_snapshot_does_not_change_after_the_register_is_renamed(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);
        $result = app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, (string) Str::uuid());
        $originalName = $result->document->snapshot['register_name'];

        CashRegister::query()->whereKey($session->cash_register_id)->update(['name' => 'Nom modifié après clôture']);

        self::assertSame($originalName, $result->document->fresh()->snapshot['register_name']);
    }

    public function test_audit_events_keep_actor_context_and_reference(): void
    {
        [$session, $actor] = $this->openSessionFor([CommercialPermission::OPERATE_CASH, CommercialPermission::CLOSE_CASH]);
        app(CloseCashSession::class)->handle($session->fresh(), $actor, 0, null, (string) Str::uuid());

        $opened = CommercialAuditEvent::query()->where('event_type', 'cash.session_opened')->where('aggregate_reference', (string) $session->id)->sole();
        $closed = CommercialAuditEvent::query()->where('event_type', 'cash.session_closed')->where('aggregate_reference', (string) $session->id)->sole();

        self::assertSame($actor->identity->reference, $opened->actor_core_reference);
        self::assertSame($session->context_id, $opened->context_id);
        self::assertSame($actor->identity->reference, $closed->actor_core_reference);
        self::assertSame($session->context_id, $closed->context_id);
    }

    public function test_historical_cash_payments_are_not_backfilled_into_a_new_session(): void
    {
        $context = $this->context();
        $product = $this->product($context);
        $historicalSale = Sale::query()->create([
            'context_id' => $context->id, 'status' => Sale::STATUS_CONFIRMED, 'currency' => 'XOF',
            'total_xof' => 1500, 'created_by_core_reference' => 'IDN-HISTORICAL',
            'confirmed_by_core_reference' => 'IDN-HISTORICAL', 'confirmed_at' => now()->subDays(30),
        ]);
        $historicalPayment = Payment::query()->create([
            'context_id' => $context->id, 'sale_id' => $historicalSale->id, 'method' => Payment::METHOD_CASH,
            'amount_xof' => 1500, 'status' => Payment::STATUS_CONFIRMED, 'actor_core_reference' => 'IDN-HISTORICAL',
            'paid_at' => now()->subDays(30), 'idempotency_key' => 'historical-pre-lot-003',
        ]);

        [$session] = $this->openSessionFor([CommercialPermission::OPERATE_CASH]);

        self::assertSame(0, CashMovement::query()->where('payment_id', $historicalPayment->id)->count());
        self::assertSame(0, app(CashBalanceCalculator::class)->expected($session));
    }

    public function test_lot_001_sales_still_work_with_a_real_session(): void
    {
        [$sale, $actor, $product] = $this->readySaleWithOpenSession(withProductReturn: true);
        StockBalance::query()->updateOrCreate(['context_id' => $product->context_id, 'product_id' => $product->id], ['quantity' => '10']);

        $result = app(ConfirmCashSale::class)->handle($sale->fresh(), $actor, 'idem-regression-sale-1');

        self::assertSame(Sale::STATUS_CONFIRMED, $result->sale->status);
        self::assertSame('8.000', (string) StockBalance::query()->where('product_id', $product->id)->sole()->quantity);
    }

    public function test_lot_002_purchases_still_work_with_a_real_session(): void
    {
        [$order, $actor] = $this->readyReceivedPurchaseOrder(unitCostXof: 1000, quantity: '4', openingAmountXof: 50000);

        $payment = app(RecordCashPurchasePayment::class)->handle($order->fresh(), $actor, 'idem-regression-purchase-1');

        self::assertSame(PurchaseOrder::STATUS_RECEIVED, $order->fresh()->status);
        self::assertSame(4000, $payment->amount_xof);
        self::assertSame(1, Payment::query()->where('purchase_order_id', $order->id)->count());
    }

    public function test_non_cash_payment_methods_are_never_conflated_with_cash(): void
    {
        $context = $this->context();
        $sale = Sale::query()->create([
            'context_id' => $context->id, 'status' => Sale::STATUS_CONFIRMED, 'currency' => 'XOF',
            'total_xof' => 2000, 'created_by_core_reference' => 'IDN-STRUCTURAL',
            'confirmed_by_core_reference' => 'IDN-STRUCTURAL', 'confirmed_at' => now(),
        ]);

        // Simule un futur moyen de paiement non-CASH sans élargir le domaine LOT-003 : créer un
        // Payment directement (hors ConfirmCashSale/RecordCashPurchasePayment, les deux seuls
        // points d'entrée qui écrivent la caisse) ne doit jamais produire de CashMovement — aucun
        // observer/hook implicite ne relie Payment à la caisse (§4, §32).
        $payment = Payment::query()->create([
            'context_id' => $context->id, 'sale_id' => $sale->id, 'method' => Payment::METHOD_CASH,
            'amount_xof' => 2000, 'status' => Payment::STATUS_CONFIRMED, 'actor_core_reference' => 'IDN-STRUCTURAL',
            'paid_at' => now(), 'idempotency_key' => 'structural-direct-payment',
        ]);

        self::assertSame(0, CashMovement::query()->where('payment_id', $payment->id)->count());
    }

    /**
     * @return array{0: Sale, 1: CurrentActor, 2?: Product}
     */
    private function readySaleWithOpenSession(bool $withProductReturn = false): array
    {
        $context = $this->context();
        $product = $this->product($context, ['sale_price_xof' => 1000]);
        StockBalance::query()->create(['context_id' => $context->id, 'product_id' => $product->id, 'quantity' => 100]);
        $actor = $this->actor($context, [CommercialPermission::SELL, CommercialPermission::OPERATE_CASH]);
        app(OpenCashSession::class)->handle($this->register($context), $actor, 0, (string) Str::uuid());

        $sale = app(SaleDraftService::class)->findOrCreateDraft($context, $actor->identity);
        app(SaleDraftService::class)->addOrIncrementLine($sale, $product, '2');

        return $withProductReturn ? [$sale->fresh(), $actor, $product] : [$sale->fresh(), $actor];
    }

    /**
     * @return array{0: PurchaseOrder, 1: CurrentActor}
     */
    private function readyReceivedPurchaseOrder(
        int $unitCostXof = 1000,
        string $quantity = '3',
        int $openingAmountXof = 1_000_000,
        bool $withOpenSession = true,
    ): array {
        $context = $this->context();
        $actor = $this->actor($context, [
            CommercialPermission::MANAGE_PURCHASES, CommercialPermission::RECEIVE_PURCHASES,
            CommercialPermission::PAY_PURCHASES, CommercialPermission::OPERATE_CASH,
        ]);

        if ($withOpenSession) {
            app(OpenCashSession::class)->handle($this->register($context), $actor, $openingAmountXof, (string) Str::uuid());
        }

        $supplier = app(SupplierManager::class)->create($context, $actor->identity, ['display_name' => 'Fournisseur de test']);
        $product = $this->product($context);
        $order = app(PurchaseOrderDraftService::class)->createDraft($context, $actor->identity, $supplier);
        $line = app(PurchaseOrderDraftService::class)->addOrUpdateLine($order, $product, $quantity, $unitCostXof);
        $confirmed = app(ConfirmPurchaseOrder::class)->handle($order, $actor, 'idem-setup-order-'.$order->id)->purchaseOrder;
        $received = app(ReceivePurchaseOrder::class)->handle($confirmed, $actor, [$line->id => $quantity], 'idem-setup-receive-'.$order->id)->purchaseOrder;

        return [$received, $actor];
    }

    /**
     * @return array{0: CashSession, 1: CurrentActor}
     */
    private function openSessionFor(array $permissions, int $openingAmountXof = 0, ?string $reference = null): array
    {
        $context = $this->context();
        $actor = $this->actor($context, $permissions, $reference);
        $session = app(OpenCashSession::class)->handle($this->register($context), $actor, $openingAmountXof, (string) Str::uuid());

        return [$session, $actor];
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

    private function member(CommercialContext $context, string $reference, array $permissions): CommercialContextMember
    {
        return CommercialContextMember::query()->create([
            'context_id' => $context->id,
            'core_identity_reference' => $reference,
            'permissions' => $permissions,
            'status' => CommercialContextMember::STATUS_ACTIVE,
        ]);
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
