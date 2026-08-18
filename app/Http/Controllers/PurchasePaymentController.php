<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\RecordCashPurchasePayment;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\InsufficientCashBalanceException;
use App\Domain\Commerce\Exceptions\NoOpenCashSessionException;
use App\Domain\Commerce\Exceptions\PurchaseOrderNotPayableException;
use App\Domain\Identity\CurrentActor;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Paiement comptant fournisseur (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §24) :
 * confirmation explicite avant écriture financière, aucune dette ni échéancier affichés. Depuis
 * LOT-003, le paiement CASH exige une session de caisse ouverte pour l'acteur — un message humain
 * (jamais une erreur technique) invite à ouvrir la caisse plutôt que d'échouer silencieusement.
 */
final class PurchasePaymentController extends Controller
{
    public function store(PurchaseOrder $purchaseOrder, RecordCashPurchasePayment $service): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        try {
            $service->handle($purchaseOrder, $actor, (string) Str::uuid());
        } catch (NoOpenCashSessionException $e) {
            return redirect()->route('purchases.show', $purchaseOrder)
                ->with('error', $e->getMessage())
                ->with('cashRegisterClosed', true);
        } catch (InsufficientCashBalanceException $e) {
            return redirect()->route('purchases.show', $purchaseOrder)
                ->with('error', sprintf('Solde de caisse insuffisant : %d F disponible(s), %d F demandé(s).', $e->availableXof, $e->requestedXof));
        } catch (PurchaseOrderNotPayableException|CommercialContextSuspendedException $e) {
            return redirect()->route('purchases.show', $purchaseOrder)->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchaseOrder)->with('status', 'Paiement enregistré.');
    }
}
