<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\CoreSessionGateway;
use App\Domain\Identity\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'acteur canonique pour la requête (sans exiger sa présence — voir RequireCurrentActor
 * pour les routes qui l'exigent). Partage $currentActor aux vues dans tous les cas.
 */
final class ResolveCurrentActor
{
    public function __construct(private readonly CoreSessionGateway $gateway) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->gateway->resolve($request);

        if ($identity !== null) {
            $actor = new CurrentActor($identity);
            app()->instance(CurrentActor::class, $actor);
            $request->attributes->set('gpos_current_actor', $actor);
        }

        view()->share('currentActor', $request->attributes->get('gpos_current_actor'));

        return $next($request);
    }
}
