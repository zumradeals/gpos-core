<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\OpenCashSession;
use App\Domain\Commerce\Exceptions\CashSessionNotOpenableException;
use App\Domain\Commerce\Exceptions\CommercialContextSuspendedException;
use App\Domain\Identity\CurrentActor;
use App\Models\CashRegister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CashSessionController extends Controller
{
    public function store(Request $request, CashRegister $cashRegister, OpenCashSession $service): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($cashRegister->context_id === $actor->activeContext()?->id, 404);

        $data = $request->validate([
            'opening_amount_xof' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $service->handle($cashRegister, $actor, (int) $data['opening_amount_xof'], (string) Str::uuid());
        } catch (CashSessionNotOpenableException|CommercialContextSuspendedException $e) {
            return redirect()->route('cash.hub')->with('error', $e->getMessage());
        }

        return redirect()->route('cash.hub')->with('status', 'Caisse ouverte.');
    }
}
