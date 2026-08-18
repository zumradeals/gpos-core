<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Paiement fournisseur borné (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §17.2) : commande
 * obligatoirement RECEIVED, paiement unique.
 */
final class PurchaseOrderNotPayableException extends RuntimeException
{
    public static function notFullyReceived(): self
    {
        return new self('Cette commande doit être totalement reçue avant tout paiement.');
    }

    public static function nothingToPay(): self
    {
        return new self('Aucun montant à régler pour cette commande.');
    }
}
