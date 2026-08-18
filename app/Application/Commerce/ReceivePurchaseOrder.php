<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\OverReceiptException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotReceivableException;
use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CurrentActor;
use App\Models\CommercialContext;
use App\Models\CommercialContextSequence;
use App\Models\CommercialDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Réception fournisseur — opération de haute intégrité (docs/implementation/LOT-002-PURCHASING-
 * SUPPLY.md §14). Ordre de verrou : PurchaseOrder, puis PurchaseOrderLines concernées, puis
 * StockBalances — le même ordre partout garantit qu'une réception concurrente et une confirmation
 * de commande se sérialisent toujours proprement, et qu'une sur-réception ne peut jamais passer.
 *
 * @param  array<string, string>  $receivedQuantities  purchase_order_line_id => quantité reçue maintenant (chaîne décimale)
 */
final class ReceivePurchaseOrder
{
    public function __construct(
        private readonly SequenceGenerator $sequences,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(PurchaseOrder $order, CurrentActor $actor, array $receivedQuantities, string $idempotencyKey): ReceivePurchaseOrderResult
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $order->context_id, 403);
        abort_unless($actor->can(CommercialPermission::RECEIVE_PURCHASES), 403, 'Permission RECEIVE_PURCHASES requise pour réceptionner.');

        return DB::transaction(function () use ($order, $actor, $receivedQuantities, $idempotencyKey): ReceivePurchaseOrderResult {
            $existingReceipt = PurchaseReceipt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existingReceipt !== null) {
                return $this->existingResult($existingReceipt);
            }

            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                throw PurchaseOrderNotReceivableException::wrongStatus($locked->status);
            }

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($locked->context_id)->lockForUpdate()->firstOrFail();

            if ($context->status !== CommercialContext::STATUS_ACTIVE) {
                throw new CommercialContextSuspendedException;
            }

            $requestedLines = array_filter(
                $receivedQuantities,
                static fn (string $quantity): bool => Quantity::isPositive($quantity),
            );

            if ($requestedLines === []) {
                throw PurchaseOrderNotReceivableException::nothingReceived();
            }

            $lines = PurchaseOrderLine::query()
                ->where('purchase_order_id', $locked->id)
                ->whereIn('id', array_keys($requestedLines))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requestedLines as $lineId => $quantity) {
                /** @var PurchaseOrderLine|null $line */
                $line = $lines->get($lineId);
                abort_if($line === null, 404, 'Ligne de commande introuvable.');

                $remaining = Quantity::subtract((string) $line->ordered_quantity, (string) $line->received_quantity);

                if (Quantity::compare($quantity, $remaining) > 0) {
                    throw new OverReceiptException($line->product_name_snapshot, $remaining, $quantity);
                }
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->whereKey($locked->supplier_id)->first();

            $receipt = PurchaseReceipt::query()->create([
                'context_id' => $context->id,
                'purchase_order_id' => $locked->id,
                'reference' => 'BR-'.$this->sequences->next($context, CommercialContextSequence::GOODS_RECEIPT),
                'received_by_core_reference' => $actor->identity->reference,
                'received_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $receiptLines = [];

            foreach ($requestedLines as $lineId => $quantity) {
                /** @var PurchaseOrderLine $line */
                $line = $lines->get($lineId);

                $lineTotal = Quantity::moneyForUnitPrice($line->unit_cost_xof, $quantity);

                $receiptLine = PurchaseReceiptLine::query()->create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_name_snapshot' => $line->product_name_snapshot,
                    'unit_label_snapshot' => $line->unit_label_snapshot,
                    'quantity' => $quantity,
                    'unit_cost_xof' => $line->unit_cost_xof,
                    'line_total_xof' => $lineTotal,
                    'track_stock_snapshot' => $line->track_stock_snapshot,
                ]);

                $receiptLines[] = $receiptLine;

                if ($line->track_stock_snapshot && $line->product_id !== null) {
                    $balance = StockBalance::query()
                        ->where('context_id', $context->id)
                        ->where('product_id', $line->product_id)
                        ->lockForUpdate()
                        ->first();

                    $before = $balance?->quantity ?? '0.000';

                    StockMovement::query()->create([
                        'context_id' => $context->id,
                        'product_id' => $line->product_id,
                        'sale_line_id' => null,
                        'purchase_receipt_line_id' => $receiptLine->id,
                        'direction' => StockMovement::DIRECTION_IN,
                        'quantity' => $quantity,
                        'reason' => 'Réception fournisseur',
                        'source_type' => 'PURCHASE_RECEIPT',
                        'source_reference' => (string) $receipt->id,
                        'actor_core_reference' => $actor->identity->reference,
                        'occurred_at' => now(),
                        'idempotency_key' => 'PURCHASE_RECEIPT_LINE_STOCK_IN:'.$receiptLine->id,
                    ]);

                    if ($balance === null) {
                        StockBalance::query()->create([
                            'context_id' => $context->id,
                            'product_id' => $line->product_id,
                            'quantity' => Quantity::add((string) $before, $quantity),
                        ]);
                    } else {
                        $balance->update(['quantity' => Quantity::add((string) $before, $quantity)]);
                    }
                }

                $line->update(['received_quantity' => Quantity::add((string) $line->received_quantity, $quantity)]);
            }

            $allLines = $locked->lines()->get();
            $fullyReceived = $allLines->every(
                fn (PurchaseOrderLine $l) => Quantity::compare((string) $l->received_quantity, (string) $l->ordered_quantity) >= 0
            );

            $locked->update(['status' => $fullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);

            $document = CommercialDocument::query()->create([
                'context_id' => $context->id,
                'purchase_receipt_id' => $receipt->id,
                'document_type' => CommercialDocument::TYPE_GOODS_RECEIPT,
                'number' => $receipt->reference,
                'snapshot' => $this->buildReceiptSnapshot($locked, $receipt, $receiptLines, $supplier, $context),
                'issued_at' => now(),
                'issued_by_core_reference' => $actor->identity->reference,
            ]);

            $this->audit->record($context, $actor->identity, 'purchase.receipt_confirmed', 'PurchaseReceipt', (string) $receipt->id, null, [
                'reference' => $receipt->reference, 'purchase_order_id' => (string) $locked->id,
            ], requestReference: $idempotencyKey);

            $this->audit->record($context, $actor->identity, 'stock.received', 'PurchaseReceipt', (string) $receipt->id, null, [
                'lines' => count($receiptLines),
            ], requestReference: $idempotencyKey);

            $this->audit->record($context, $actor->identity, 'document.issued', 'CommercialDocument', (string) $document->id, null, [
                'number' => $document->number,
            ], requestReference: $idempotencyKey);

            return new ReceivePurchaseOrderResult($locked->refresh(), $receipt, $document);
        });
    }

    private function existingResult(PurchaseReceipt $receipt): ReceivePurchaseOrderResult
    {
        $document = $receipt->document()->firstOrFail();
        $order = $receipt->purchaseOrder()->firstOrFail();

        return new ReceivePurchaseOrderResult($order, $receipt, $document);
    }

    private function buildReceiptSnapshot(PurchaseOrder $order, PurchaseReceipt $receipt, array $receiptLines, Supplier $supplier, CommercialContext $context): array
    {
        return [
            'context_display_name' => $context->display_name,
            'currency' => $order->currency,
            'purchase_order_reference' => $order->reference,
            'reference' => $receipt->reference,
            'received_at' => $receipt->received_at->toIso8601String(),
            'supplier_display_name' => $supplier->display_name,
            'lines' => array_map(fn (PurchaseReceiptLine $line) => [
                'product_name' => $line->product_name_snapshot,
                'unit_label' => $line->unit_label_snapshot,
                'unit_cost_xof' => $line->unit_cost_xof,
                'quantity' => (string) $line->quantity,
                'line_total_xof' => $line->line_total_xof,
            ], $receiptLines),
            'total_xof' => (int) array_sum(array_map(fn (PurchaseReceiptLine $line) => $line->line_total_xof, $receiptLines)),
        ];
    }
}
