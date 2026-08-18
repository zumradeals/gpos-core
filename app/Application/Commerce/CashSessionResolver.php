<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\Exceptions\NoOpenCashSessionException;
use App\Domain\Identity\CoreIdentityReference;
use App\Models\CashSession;
use App\Models\CommercialContext;

/**
 * Résolution de la session active (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §14) :
 * une opération CASH utilise uniquement l'unique session OPEN dont responsible_core_reference
 * correspond à l'acteur courant et context_id au contexte courant. Jamais de choix silencieux de
 * la caisse d'un autre acteur.
 */
final class CashSessionResolver
{
    public function findOpenSessionForActor(CommercialContext $context, CoreIdentityReference $actor): ?CashSession
    {
        return CashSession::query()
            ->where('context_id', $context->id)
            ->where('responsible_core_reference', $actor->reference)
            ->where('status', CashSession::STATUS_OPEN)
            ->first();
    }

    public function requireOpenSessionForActor(CommercialContext $context, CoreIdentityReference $actor): CashSession
    {
        $session = $this->findOpenSessionForActor($context, $actor);

        if ($session === null) {
            throw NoOpenCashSessionException::forActor();
        }

        return $session;
    }
}
