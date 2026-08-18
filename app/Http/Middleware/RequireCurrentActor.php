<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque les routes qui exigent un acteur résolu. Tant que la fédération GAMAD Core n'est pas
 * branchée, ceci arrive en production (aucune passerelle réelle n'y est encore liée) : l'écran
 * reste honnête plutôt que de créer un contournement.
 */
final class RequireCurrentActor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound(CurrentActor::class)) {
            return response()->view('identity.unresolved', [], 401);
        }

        return $next($request);
    }
}
