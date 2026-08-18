<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Une commande ayant une réception, même partielle, ne peut pas être annulée dans LOT-002
 * (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §16).
 */
final class PurchaseOrderNotCancellableException extends RuntimeException
{
    public static function alreadyReceived(): self
    {
        return new self('Cette commande a déjà été (au moins partiellement) reçue et ne peut plus être annulée.');
    }

    public static function wrongStatus(string $status): self
    {
        return new self("Cette commande ne peut pas être annulée dans son état actuel ({$status}).");
    }
}
