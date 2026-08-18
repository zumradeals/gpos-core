<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque une route si l'acteur n'a pas la permission commerciale demandée dans son contexte actif.
 * Ne jamais afficher une action interdite puis répondre « accès refusé » lorsqu'on peut connaître
 * la permission à l'avance (docs/product/DESIGN-DIRECTION.md §11) — les vues lisent aussi
 * $currentActor->can() pour adapter l'affichage.
 */
final class RequireCommercialPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        if (! $actor->can($permission)) {
            return response()->view('identity.permission-denied', ['permission' => $permission], 403);
        }

        return $next($request);
    }
}
