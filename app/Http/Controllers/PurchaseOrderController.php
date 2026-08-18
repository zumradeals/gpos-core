<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\CancelPurchaseOrder;
use App\Application\Commerce\PurchaseOrderDraftService;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotCancellableException;
use App\Domain\Identity\CurrentActor;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Commande d'achat (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §22) : choisir/créer
 * fournisseur, puis construire la commande dans le composant Livewire tant qu'elle est DRAFT.
 */
final class PurchaseOrderController extends Controller
{
    public function create(Request $request): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($actor->can(CommercialPermission::MANAGE_PURCHASES), 403);

        $suppliers = Supplier::query()
            ->where('context_id', $actor->activeContext()->id)
            ->where('active', true)
            ->orderBy('display_name')
            ->get();

        return view('purchases.create', [
            'suppliers' => $suppliers,
            'productId' => $request->query('produit'),
        ]);
    }

    public function store(Request $request, PurchaseOrderDraftService $drafts): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($actor->can(CommercialPermission::MANAGE_PURCHASES), 403);

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid'],
            'produit' => ['nullable', 'uuid'],
        ]);

        $supplier = Supplier::query()->where('context_id', $actor->activeContext()->id)->findOrFail($data['supplier_id']);

        $order = $drafts->createDraft($actor->activeContext(), $actor->identity, $supplier);

        return redirect()->route('purchases.show', array_filter(['purchaseOrder' => $order->id, 'produit' => $data['produit'] ?? null]));
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($purchaseOrder->context_id === $actor->activeContext()->id, 404);
        abort_unless($actor->can(CommercialPermission::VIEW_PURCHASES) || $actor->can(CommercialPermission::MANAGE_PURCHASES), 403);

        if ($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT) {
            abort_unless($actor->can(CommercialPermission::MANAGE_PURCHASES), 403);

            return view('purchases.builder', [
                'purchaseOrderId' => (string) $purchaseOrder->id,
                'initialProductId' => $request->query('produit'),
            ]);
        }

        $purchaseOrder->load(['supplier', 'lines', 'receipts.document', 'payment', 'document']);

        $canCancel = $actor->can(CommercialPermission::MANAGE_PURCHASES)
            && ($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT
                || ($purchaseOrder->status === PurchaseOrder::STATUS_ORDERED && $purchaseOrder->receipts->isEmpty()));

        return view('purchases.show', [
            'order' => $purchaseOrder,
            'canReceive' => $actor->can(CommercialPermission::RECEIVE_PURCHASES),
            'canPay' => $actor->can(CommercialPermission::PAY_PURCHASES),
            'canManagePurchases' => $actor->can(CommercialPermission::MANAGE_PURCHASES),
            'canCancel' => $canCancel,
        ]);
    }

    public function cancel(PurchaseOrder $purchaseOrder, CancelPurchaseOrder $canceller): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        try {
            $canceller->handle($purchaseOrder, $actor);
        } catch (PurchaseOrderNotCancellableException $e) {
            return redirect()->route('purchases.show', $purchaseOrder)->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchaseOrder)->with('status', 'Commande annulée.');
    }
}
