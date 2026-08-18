<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use Illuminate\Http\Request;

/**
 * Contrat de résolution de l'identité canonique depuis une requête HTTP.
 *
 * GAMAD Core est l'autorité d'identité (docs/G-POS-DOCTRINE.md §2). L'implémentation réelle de
 * fédération/continuation Core est hors périmètre LOT-001 (docs/architecture/SATELLITE-CONTRACT.md
 * §5) : seule une implémentation de développement existe pour l'instant, jamais activable en
 * production. Une future implémentation fédérée respectera ce même contrat.
 */
interface CoreSessionGateway
{
    public function resolve(Request $request): ?CoreIdentityReference;
}
