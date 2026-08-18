<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\CashBalanceCalculator;
use App\Application\Commerce\CashSessionResolver;
use App\Application\Commerce\CloseCashSession;
use App\Domain\Commerce\Exceptions\CashSessionNotCloseableException;
use App\Domain\Commerce\Exceptions\CashVarianceReasonRequiredException;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Commerce\Exceptions\NoOpenCashSessionException;
use App\Domain\Identity\CurrentActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Clôture de caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §17, §24). L'écran ne
 * gère que la propre session de l'acteur — la clôture d'une session d'un autre acteur par
 * MANAGE_CASH reste une capacité de service, sans UI dédiée dans ce lot.
 */
final class CashClosureController extends Controller
{
    public function create(CashSessionResolver $resolver, CashBalanceCalculator $calculator): View|RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        try {
            $session = $resolver->requireOpenSessionForActor($actor->activeContext(), $actor->identity);
        } catch (NoOpenCashSessionException $e) {
            return redirect()->route('cash.hub')->with('error', $e->getMessage());
        }

        return view('cash.closure', [
            'session' => $session,
            'expected' => $calculator->expected($session),
        ]);
    }

    public function store(Request $request, CashSessionResolver $resolver, CloseCashSession $service): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        $data = $request->validate([
            'counted_amount_xof' => ['required', 'integer', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $session = $resolver->requireOpenSessionForActor($actor->activeContext(), $actor->identity);
            $service->handle($session, $actor, (int) $data['counted_amount_xof'], $data['variance_reason'] ?? null, (string) Str::uuid());
        } catch (NoOpenCashSessionException|CashSessionNotCloseableException|CommercialContextSuspendedException $e) {
            return redirect()->route('cash.hub')->with('error', $e->getMessage());
        } catch (CashVarianceReasonRequiredException $e) {
            return redirect()->route('cash.closure.create')
                ->withInput()
                ->with('error', $e->getMessage())
                ->with('variance_xof', $e->varianceXof);
        }

        return redirect()->route('cash.hub')->with('status', 'Caisse clôturée.');
    }
}
