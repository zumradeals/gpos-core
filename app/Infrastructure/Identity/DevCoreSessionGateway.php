<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CoreSessionGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Passerelle de développement — jamais une fédération GAMAD Core réelle.
 *
 * Double verrou contre une activation silencieuse en production
 * (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.3) :
 *
 * 1. App\Providers\IdentityServiceProvider ne lie cette classe que si l'environnement n'est
 *    pas "production" ;
 * 2. cette classe elle-même refuse de s'exécuter si l'environnement est "production", même si
 *    elle était liée par erreur (mauvaise config publiée, cache de conteneur obsolète, etc.).
 *
 * La session HTTP permet de simuler différents acteurs de développement (utile pour QA manuelle
 * et pour les tests) sans jamais devenir une source d'identité canonique : elle ne fait que
 * sélectionner QUELLE référence de développement est active pour cette requête.
 */
final class DevCoreSessionGateway implements CoreSessionGateway
{
    public const SESSION_KEY = 'gpos_dev_identity_reference';

    public const SESSION_LABEL_KEY = 'gpos_dev_identity_label';

    public function __construct(private readonly Application $app)
    {
        $this->assertNotProduction();
    }

    public function resolve(Request $request): ?CoreIdentityReference
    {
        $this->assertNotProduction();

        $reference = $request->session()->get(self::SESSION_KEY)
            ?? config('gpos.dev_identity.core_identity_reference');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $label = $request->session()->get(self::SESSION_LABEL_KEY)
            ?? config('gpos.dev_identity.core_identity_label');

        return new CoreIdentityReference($reference, is_string($label) ? $label : null);
    }

    private function assertNotProduction(): void
    {
        if ($this->app->environment('production')) {
            throw new RuntimeException(
                'DevCoreSessionGateway ne peut jamais être exécutée en production. '
                .'Voir docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.3.'
            );
        }
    }
}
