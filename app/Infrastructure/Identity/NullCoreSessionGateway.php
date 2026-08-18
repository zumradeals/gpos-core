<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identity\CoreIdentityReference;
use App\Domain\Identity\CoreSessionGateway;
use Illuminate\Http\Request;

/**
 * Passerelle par défaut tant qu'aucune fédération GAMAD Core réelle n'est branchée et que
 * l'identité de développement est désactivée. Ne résout jamais personne — le comportement sûr par
 * défaut plutôt qu'un accès implicite.
 */
final class NullCoreSessionGateway implements CoreSessionGateway
{
    public function resolve(Request $request): ?CoreIdentityReference
    {
        return null;
    }
}
