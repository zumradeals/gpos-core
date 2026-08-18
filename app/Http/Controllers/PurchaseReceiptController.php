<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use App\Models\PurchaseOrder;
use Illuminate\View\View;

/**
 * « Que venez-vous de recevoir ? » (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §23).
 */
final class PurchaseReceiptController extends Controller
{
    public function create(PurchaseOrder $purchaseOrder): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($purchaseOrder->context_id === $actor->activeContext()->id, 404);
        abort_unless($actor->can(CommercialPermission::RECEIVE_PURCHASES), 403);
        abort_unless(in_array($purchaseOrder->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true), 404);

        return view('purchases.receive', ['purchaseOrderId' => (string) $purchaseOrder->id]);
    }
}
