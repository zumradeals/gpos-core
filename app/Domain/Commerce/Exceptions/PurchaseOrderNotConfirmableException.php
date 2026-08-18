<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Symétrique de SaleNotConfirmableException pour l'achat (docs/implementation/LOT-002-PURCHASING-
 * SUPPLY.md §12).
 */
final class PurchaseOrderNotConfirmableException extends RuntimeException
{
    public static function alreadyCancelled(): self
    {
        return new self('Cette commande a été annulée et ne peut plus être confirmée.');
    }

    public static function empty(): self
    {
        return new self('Ajoutez au moins un article avant de confirmer la commande.');
    }
}
