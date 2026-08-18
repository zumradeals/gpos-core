<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

final class SaleNotConfirmableException extends RuntimeException
{
    public static function alreadyCancelled(): self
    {
        return new self('Cette vente a été annulée et ne peut plus être confirmée.');
    }

    public static function empty(): self
    {
        return new self('Ajoutez au moins un article avant d’encaisser.');
    }
}
