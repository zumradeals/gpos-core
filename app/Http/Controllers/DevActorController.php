<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Identity\DevCoreSessionGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Outil de développement pour « devenir » une référence Core arbitraire et QA manuellement les
 * écrans de permission. Enregistré uniquement hors production — voir routes/web.php.
 */
final class DevActorController extends Controller
{
    public function show(): View
    {
        return view('dev.actor');
    }

    public function become(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'core_identity_reference' => ['required', 'string', 'max:80'],
            'core_identity_label' => ['nullable', 'string', 'max:120'],
        ]);

        $request->session()->put(DevCoreSessionGateway::SESSION_KEY, $data['core_identity_reference']);
        $request->session()->put(DevCoreSessionGateway::SESSION_LABEL_KEY, $data['core_identity_label'] ?? null);

        return redirect()->route('home');
    }
}
