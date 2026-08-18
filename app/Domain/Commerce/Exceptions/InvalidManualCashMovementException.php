<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

final class InvalidManualCashMovementException extends RuntimeException
{
    public static function invalidAmount(): self
    {
        return new self('Le montant doit être un entier strictement positif.');
    }

    public static function reasonRequired(): self
    {
        return new self('Indiquez un motif pour ce mouvement.');
    }
}
