<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\RecordCashPurchasePayment;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotPayableException;
use App\Domain\Identity\CurrentActor;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Paiement comptant fournisseur (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §24) :
 * confirmation explicite avant écriture financière, aucune dette ni échéancier affichés.
 */
final class PurchasePaymentController extends Controller
{
    public function store(PurchaseOrder $purchaseOrder, RecordCashPurchasePayment $service): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        try {
            $service->handle($purchaseOrder, $actor, (string) Str::uuid());
        } catch (PurchaseOrderNotPayableException|CommercialContextSuspendedException $e) {
            return redirect()->route('purchases.show', $purchaseOrder)->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchaseOrder)->with('status', 'Paiement enregistré.');
    }
}
