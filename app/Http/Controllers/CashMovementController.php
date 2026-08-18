<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\CashSessionResolver;
use App\Application\Commerce\RecordManualCashMovement;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\InsufficientCashBalanceException;
use App\Domain\Commerce\Exceptions\InvalidManualCashMovementException;
use App\Domain\Commerce\Exceptions\NoOpenCashSessionException;
use App\Domain\Identity\CurrentActor;
use App\Models\CashMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Mouvement manuel (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §15) — jamais présenté
 * comme une vente ou un achat fournisseur. L'ID de session n'est jamais un paramètre : le service
 * résout toujours l'unique session OPEN de l'acteur courant.
 */
final class CashMovementController extends Controller
{
    public function store(Request $request, CashSessionResolver $resolver, RecordManualCashMovement $service): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        $data = $request->validate([
            'direction' => ['required', Rule::in([CashMovement::DIRECTION_IN, CashMovement::DIRECTION_OUT])],
            'amount_xof' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $session = $resolver->requireOpenSessionForActor($actor->activeContext(), $actor->identity);
            $service->handle($session, $actor, $data['direction'], (int) $data['amount_xof'], $data['reason'], (string) Str::uuid());
        } catch (NoOpenCashSessionException|InvalidManualCashMovementException|InsufficientCashBalanceException|CommercialContextSuspendedException $e) {
            return redirect()->route('cash.hub')->with('error', $e->getMessage());
        }

        return redirect()->route('cash.hub')->with('status', 'Mouvement enregistré.');
    }
}
