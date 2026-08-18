<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\CurrentActor;
use App\Models\CommercialContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque les routes qui exigent un contexte commercial actif. Sans contexte sélectionné,
 * l'utilisateur voit l'écran de sélection plutôt qu'une page qui suppose un contexte inexistant
 * (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §20 — « absence de contexte actif »).
 */
final class RequireActiveCommercialContext
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        if (! $actor->hasActiveContext()) {
            return response()->view('contexts.switch', [], 409);
        }

        if ($actor->activeContext()->status !== CommercialContext::STATUS_ACTIVE) {
            return response()->view('contexts.suspended', ['context' => $actor->activeContext()], 423);
        }

        return $next($request);
    }
}
