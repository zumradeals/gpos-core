<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\InsufficientStockException;
use App\Domain\Commerce\Exceptions\SaleNotConfirmableException;
use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CurrentActor;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CommercialContext;
use App\Models\CommercialContextSequence;
use App\Models\CommercialDocument;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Transaction de confirmation d'une vente comptant (docs/implementation/LOT-001-APP-SHELL-
 * COMMERCE-SLICE.md §15). Verrou de ligne sur la vente puis sur les soldes de stock concernés,
 * dans cet ordre, sur toute exécution — évite les inter-blocages entre confirmations concurrentes
 * portant sur des ventes différentes mais des produits partagés.
 *
 * Modification bornée LOT-003 (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §13.1) : dans
 * la même transaction, une vente CASH d'un montant réel exige désormais une session de caisse
 * ouverte pour l'acteur et produit exactement un CashMovement SALE_PAYMENT lié au Payment — sinon
 * rollback complet, la vente ne devient jamais CONFIRMED. Une vente à 0 XOF (aucune espèce
 * échangée) ne touche pas la caisse.
 */
final class ConfirmCashSale
{
    public function __construct(
        private readonly SequenceGenerator $sequences,
        private readonly AuditLogger $audit,
        private readonly CashSessionResolver $cashSessionResolver,
        private readonly CashLedger $cashLedger,
    ) {}

    public function handle(Sale $sale, CurrentActor $actor, string $idempotencyKey): ConfirmSaleResult
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $sale->context_id, 403);
        abort_unless($actor->can(CommercialPermission::SELL), 403, 'Permission SELL requise pour encaisser.');

        return DB::transaction(function () use ($sale, $actor, $idempotencyKey): ConfirmSaleResult {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Sale::STATUS_CONFIRMED) {
                return $this->existingResult($locked);
            }

            if ($locked->status === Sale::STATUS_CANCELLED) {
                throw SaleNotConfirmableException::alreadyCancelled();
            }

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($locked->context_id)->lockForUpdate()->firstOrFail();

            if ($context->status !== CommercialContext::STATUS_ACTIVE) {
                throw new CommercialContextSuspendedException;
            }

            $lines = $locked->lines()->orderBy('created_at')->get();

            if ($lines->isEmpty()) {
                throw SaleNotConfirmableException::empty();
            }

            // Totaux recalculés depuis les lignes en base — jamais depuis une valeur envoyée par
            // le client (docs/implementation/LOT-001 §11.2).
            $subtotal = (int) $lines->sum('line_total_xof');
            $total = max(0, $subtotal - $locked->discount_xof);

            $trackedLines = $lines->where('track_stock_snapshot', true);

            if ($trackedLines->isNotEmpty()) {
                $balances = StockBalance::query()
                    ->where('context_id', $context->id)
                    ->whereIn('product_id', $trackedLines->pluck('product_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                foreach ($trackedLines as $line) {
                    $balance = $balances->get($line->product_id);
                    $available = $balance?->quantity ?? '0.000';

                    if (Quantity::compare((string) $available, (string) $line->quantity) < 0) {
                        throw new InsufficientStockException($line->product_name_snapshot, (string) $available, (string) $line->quantity);
                    }
                }
            }

            $locked->update([
                'status' => Sale::STATUS_CONFIRMED,
                'reference' => 'V-'.$this->sequences->next($context, CommercialContextSequence::SALE),
                'subtotal_xof' => $subtotal,
                'total_xof' => $total,
                'confirmed_by_core_reference' => $actor->identity->reference,
                'confirmed_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($trackedLines as $line) {
                $balance = StockBalance::query()
                    ->where('context_id', $context->id)
                    ->where('product_id', $line->product_id)
                    ->lockForUpdate()
                    ->first();

                StockMovement::query()->create([
                    'context_id' => $context->id,
                    'product_id' => $line->product_id,
                    'sale_line_id' => $line->id,
                    'direction' => StockMovement::DIRECTION_OUT,
                    'quantity' => $line->quantity,
                    'reason' => 'Vente confirmée',
                    'source_type' => 'SALE',
                    'source_reference' => (string) $locked->id,
                    'actor_core_reference' => $actor->identity->reference,
                    'occurred_at' => now(),
                    'idempotency_key' => 'SALE_LINE_STOCK_OUT:'.$line->id,
                ]);

                $balance->update([
                    'quantity' => Quantity::subtract((string) $balance->quantity, (string) $line->quantity),
                ]);
            }

            $payment = Payment::query()->create([
                'context_id' => $context->id,
                'sale_id' => $locked->id,
                'method' => Payment::METHOD_CASH,
                'amount_xof' => $total,
                'status' => Payment::STATUS_CONFIRMED,
                'actor_core_reference' => $actor->identity->reference,
                'paid_at' => now(),
                'idempotency_key' => 'SALE_PAYMENT:'.$locked->id,
            ]);

            // Une vente à 0 XOF n'échange aucune espèce : la caisse n'est pas concernée. Sinon,
            // aucun paiement CASH ne peut être confirmé sans session ouverte et autorisée — la
            // vente entière échoue/rollback si l'écriture caisse échoue (§13.1).
            if ($total > 0) {
                abort_unless($actor->can(CommercialPermission::OPERATE_CASH), 403, 'Permission OPERATE_CASH requise pour encaisser en espèces.');

                $openSession = $this->cashSessionResolver->requireOpenSessionForActor($context, $actor->identity);

                /** @var CashSession $lockedSession */
                $lockedSession = CashSession::query()->whereKey($openSession->id)->lockForUpdate()->firstOrFail();

                $this->cashLedger->record(
                    $lockedSession,
                    CashMovement::TYPE_SALE_PAYMENT,
                    CashMovement::DIRECTION_IN,
                    $total,
                    $actor->identity,
                    'SALE_PAYMENT_CASH_MOVEMENT:'.$payment->id,
                    paymentId: (string) $payment->id,
                    sourceType: 'SALE',
                    sourceReference: (string) $locked->id,
                );

                $this->audit->record($context, $actor->identity, 'cash.payment_linked', 'Payment', (string) $payment->id, null, [
                    'cash_session_id' => (string) $lockedSession->id, 'amount_xof' => $total,
                ], requestReference: $idempotencyKey);
            }

            $document = CommercialDocument::query()->create([
                'context_id' => $context->id,
                'sale_id' => $locked->id,
                'document_type' => CommercialDocument::TYPE_RECEIPT,
                'number' => 'RC-'.$this->sequences->next($context, CommercialContextSequence::RECEIPT),
                'snapshot' => $this->buildReceiptSnapshot($locked, $lines, $payment, $context),
                'issued_at' => now(),
                'issued_by_core_reference' => $actor->identity->reference,
            ]);

            $this->audit->record($context, $actor->identity, 'sale.confirmed', 'Sale', (string) $locked->id, null, [
                'reference' => $locked->reference, 'total_xof' => $total,
            ], requestReference: $idempotencyKey);

            $this->audit->record($context, $actor->identity, 'payment.confirmed', 'Payment', (string) $payment->id, null, [
                'amount_xof' => $total,
            ], requestReference: $idempotencyKey);

            $this->audit->record($context, $actor->identity, 'document.issued', 'CommercialDocument', (string) $document->id, null, [
                'number' => $document->number,
            ], requestReference: $idempotencyKey);

            return new ConfirmSaleResult($locked->refresh(), $payment, $document);
        });
    }

    private function existingResult(Sale $sale): ConfirmSaleResult
    {
        $payment = $sale->payment()->firstOrFail();
        $document = $sale->document()->firstOrFail();

        return new ConfirmSaleResult($sale, $payment, $document);
    }

    private function buildReceiptSnapshot(Sale $sale, $lines, Payment $payment, CommercialContext $context): array
    {
        return [
            'context_display_name' => $context->display_name,
            'currency' => $sale->currency,
            'reference' => $sale->reference,
            'confirmed_at' => now()->toIso8601String(),
            'lines' => $lines->map(fn ($line) => [
                'product_name' => $line->product_name_snapshot,
                'unit_label' => $line->unit_label_snapshot,
                'unit_price_xof' => $line->unit_price_xof,
                'quantity' => (string) $line->quantity,
                'line_total_xof' => $line->line_total_xof,
            ])->all(),
            'subtotal_xof' => $sale->subtotal_xof,
            'discount_xof' => $sale->discount_xof,
            'total_xof' => $sale->total_xof,
            'payment_method' => $payment->method,
            'amount_paid_xof' => $payment->amount_xof,
        ];
    }
}
