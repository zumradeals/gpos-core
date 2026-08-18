<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Aucun nouveau paiement CASH ne peut être confirmé sans session de caisse ouverte et autorisée
 * pour l'acteur (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §4, §14, §23).
 */
final class NoOpenCashSessionException extends RuntimeException
{
    public static function forActor(): self
    {
        return new self('Ouvrez votre caisse avant d’encaisser en espèces.');
    }
}
