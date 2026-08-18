<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

final class CashSessionNotCloseableException extends RuntimeException
{
    public static function alreadyClosed(): self
    {
        return new self('Cette session de caisse est déjà clôturée.');
    }

    public static function invalidCountedAmount(): self
    {
        return new self('Le montant compté doit être un entier positif ou nul.');
    }
}
