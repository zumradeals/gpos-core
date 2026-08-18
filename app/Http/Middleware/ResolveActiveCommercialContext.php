<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\CurrentActor;
use App\Models\CommercialContext;
use App\Models\CommercialContextMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout le contexte commercial actif de l'acteur (docs/implementation/LOT-001 §7). Un même
 * humain peut appartenir à plusieurs contextes avec des permissions différentes
 * (docs/architecture/SATELLITE-CONTRACT.md §4) — la sélection est donc explicite, jamais devinée
 * au-delà du cas trivial où l'acteur n'a qu'un seul contexte actif.
 *
 * Un contexte suspendu déjà sélectionné reste résolu (pour que RequireActiveCommercialContext
 * puisse afficher l'écran « contexte suspendu » nommé) mais ne peut jamais être auto-sélectionné.
 */
final class ResolveActiveCommercialContext
{
    public const SESSION_KEY = 'gpos_active_commercial_context_id';

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound(CurrentActor::class)) {
            return $next($request);
        }

        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);

        $memberships = CommercialContextMember::query()
            ->with('context')
            ->where('core_identity_reference', $actor->identity->reference)
            ->where('status', CommercialContextMember::STATUS_ACTIVE)
            ->get();

        view()->share('myCommercialContextMemberships', $memberships);

        $selectedId = $request->session()->get(self::SESSION_KEY);
        $membership = $memberships->firstWhere('context_id', $selectedId);

        $activeMemberships = $memberships->filter(
            fn (CommercialContextMember $m) => $m->context?->status === CommercialContext::STATUS_ACTIVE
        );

        if ($membership === null && $activeMemberships->count() === 1) {
            $membership = $activeMemberships->first();
            $request->session()->put(self::SESSION_KEY, $membership->context_id);
        }

        if ($membership !== null && $membership->context !== null) {
            $actor = $actor->withActiveContext($membership->context, $membership->permissions ?? []);
            app()->instance(CurrentActor::class, $actor);
        }

        view()->share('currentActor', $actor);

        return $next($request);
    }
}
