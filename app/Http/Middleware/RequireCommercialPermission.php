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
    /**
     * Plusieurs permissions passées (route::middleware('commercial.permission:A,B')) sont
     * combinées en OU : l'acteur doit en posséder au moins une. Utile pour une route partagée par
     * plusieurs rôles (p. ex. le hub Caisse, accessible dès qu'une seule permission caisse est
     * pertinente).
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        foreach ($permissions as $permission) {
            if ($actor->can($permission)) {
                return $next($request);
            }
        }

        return response()->view('identity.permission-denied', ['permission' => $permissions[0] ?? ''], 403);
    }
}
