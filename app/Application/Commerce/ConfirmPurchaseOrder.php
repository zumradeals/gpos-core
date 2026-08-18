<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotConfirmableException;
use App\Domain\Identity\CurrentActor;
use App\Models\CommercialContext;
use App\Models\CommercialContextSequence;
use App\Models\CommercialDocument;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Transaction de confirmation d'une commande d'achat (docs/implementation/LOT-002-PURCHASING-
 * SUPPLY.md §12). Verrou de ligne sur la commande — même ordre que PurchaseOrderDraftService et
 * ReceivePurchaseOrder — puis sur le fournisseur, avant de générer la référence et le document.
 */
final class ConfirmPurchaseOrder
{
    public function __construct(
        private readonly SequenceGenerator $sequences,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(PurchaseOrder $order, CurrentActor $actor, string $idempotencyKey): ConfirmPurchaseOrderResult
    {
        abort_unless($actor->hasActiveContext() && $actor->activeContext()->id === $order->context_id, 403);
        abort_unless($actor->can(CommercialPermission::MANAGE_PURCHASES), 403, 'Permission MANAGE_PURCHASES requise pour confirmer une commande.');

        return DB::transaction(function () use ($order, $actor, $idempotencyKey): ConfirmPurchaseOrderResult {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseOrder::STATUS_ORDERED
                || $locked->status === PurchaseOrder::STATUS_PARTIALLY_RECEIVED
                || $locked->status === PurchaseOrder::STATUS_RECEIVED) {
                if ($locked->confirmation_idempotency_key === $idempotencyKey) {
                    return $this->existingResult($locked);
                }
            }

            if ($locked->status === PurchaseOrder::STATUS_CANCELLED) {
                throw PurchaseOrderNotConfirmableException::alreadyCancelled();
            }

            abort_unless($locked->status === PurchaseOrder::STATUS_DRAFT, 409, 'Cette commande n’est plus au statut brouillon.');

            /** @var CommercialContext $context */
            $context = CommercialContext::query()->whereKey($locked->context_id)->lockForUpdate()->firstOrFail();

            if ($context->status !== CommercialContext::STATUS_ACTIVE) {
                throw new CommercialContextSuspendedException;
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->whereKey($locked->supplier_id)->lockForUpdate()->firstOrFail();

            $lines = $locked->lines()->orderBy('created_at')->get();

            if ($lines->isEmpty()) {
                throw PurchaseOrderNotConfirmableException::empty();
            }

            // Totaux recalculés depuis les lignes en base — jamais depuis une valeur envoyée par
            // le client (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §11).
            $subtotal = (int) $lines->sum('line_total_xof');

            $locked->update([
                'status' => PurchaseOrder::STATUS_ORDERED,
                'reference' => 'ACH-'.$this->sequences->next($context, CommercialContextSequence::PURCHASE_ORDER),
                'subtotal_xof' => $subtotal,
                'total_xof' => $subtotal,
                'supplier_display_name_snapshot' => $supplier->display_name,
                'ordered_by_core_reference' => $actor->identity->reference,
                'ordered_at' => now(),
                'confirmation_idempotency_key' => $idempotencyKey,
            ]);

            $document = CommercialDocument::query()->create([
                'context_id' => $context->id,
                'purchase_order_id' => $locked->id,
                'document_type' => CommercialDocument::TYPE_PURCHASE_ORDER,
                'number' => $locked->reference,
                'snapshot' => $this->buildOrderSnapshot($locked, $lines, $supplier, $context),
                'issued_at' => now(),
                'issued_by_core_reference' => $actor->identity->reference,
            ]);

            $this->audit->record($context, $actor->identity, 'purchase.ordered', 'PurchaseOrder', (string) $locked->id, null, [
                'reference' => $locked->reference, 'total_xof' => $subtotal,
            ], requestReference: $idempotencyKey);

            $this->audit->record($context, $actor->identity, 'document.issued', 'CommercialDocument', (string) $document->id, null, [
                'number' => $document->number,
            ], requestReference: $idempotencyKey);

            return new ConfirmPurchaseOrderResult($locked->refresh(), $document);
        });
    }

    private function existingResult(PurchaseOrder $order): ConfirmPurchaseOrderResult
    {
        $document = $order->document()->firstOrFail();

        return new ConfirmPurchaseOrderResult($order, $document);
    }

    private function buildOrderSnapshot(PurchaseOrder $order, $lines, Supplier $supplier, CommercialContext $context): array
    {
        return [
            'context_display_name' => $context->display_name,
            'currency' => $order->currency,
            'reference' => $order->reference,
            'ordered_at' => now()->toIso8601String(),
            'expected_on' => $order->expected_on?->toDateString(),
            'supplier_display_name' => $supplier->display_name,
            'supplier_contact_name' => $supplier->contact_name,
            'supplier_phone' => $supplier->phone,
            'lines' => $lines->map(fn ($line) => [
                'product_name' => $line->product_name_snapshot,
                'unit_label' => $line->unit_label_snapshot,
                'unit_cost_xof' => $line->unit_cost_xof,
                'ordered_quantity' => (string) $line->ordered_quantity,
                'line_total_xof' => $line->line_total_xof,
            ])->all(),
            'subtotal_xof' => $order->subtotal_xof,
            'total_xof' => $order->total_xof,
        ];
    }
}
