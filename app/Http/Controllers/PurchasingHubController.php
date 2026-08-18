<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use App\Models\PurchaseOrder;
use Illuminate\View\View;

/**
 * Hub Acheter (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §21) : à réceptionner, nouvel
 * achat, commandes récentes, fournisseurs — pas une navigation ERP surchargée.
 */
final class PurchasingHubController extends Controller
{
    public function index(): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        $context = $actor->activeContext();

        $toReceive = $actor->can(CommercialPermission::RECEIVE_PURCHASES)
            ? PurchaseOrder::query()
                ->where('context_id', $context->id)
                ->whereIn('status', [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->orderBy('ordered_at')
                ->get()
            : collect();

        $recentOrders = $actor->can(CommercialPermission::VIEW_PURCHASES)
            ? PurchaseOrder::query()
                ->where('context_id', $context->id)
                ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
                ->latest('ordered_at')
                ->limit(10)
                ->get()
            : collect();

        return view('purchases.hub', [
            'toReceive' => $toReceive,
            'recentOrders' => $recentOrders,
            'canManagePurchases' => $actor->can(CommercialPermission::MANAGE_PURCHASES),
            'canViewPurchases' => $actor->can(CommercialPermission::VIEW_PURCHASES),
        ]);
    }
}
