<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\CashRegisterManager;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CashRegisterController extends Controller
{
    public function store(Request $request, CashRegisterManager $manager): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($actor->can(CommercialPermission::MANAGE_CASH), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
        ]);

        $manager->create($actor->activeContext(), $actor->identity, $data);

        return redirect()->route('cash.hub')->with('status', 'Caisse créée.');
    }
}
