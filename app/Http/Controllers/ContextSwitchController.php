<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\CurrentActor;
use App\Http\Middleware\ResolveActiveCommercialContext;
use App\Models\CommercialContextMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ContextSwitchController extends Controller
{
    public function select(Request $request): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        $data = $request->validate(['context_id' => ['required', 'uuid']]);

        $membership = CommercialContextMember::query()
            ->where('core_identity_reference', $actor->identity->reference)
            ->where('context_id', $data['context_id'])
            ->where('status', CommercialContextMember::STATUS_ACTIVE)
            ->first();

        abort_unless($membership !== null, 403);

        $request->session()->put(ResolveActiveCommercialContext::SESSION_KEY, $membership->context_id);

        return redirect()->route('home');
    }
}
