<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Une réception ne peut jamais dépasser la quantité encore attendue (docs/implementation/LOT-002-
 * PURCHASING-SUPPLY.md §14, §15) : refuse toute la réception, atomiquement.
 */
final class OverReceiptException extends RuntimeException
{
    public function __construct(public readonly string $productName, public readonly string $remaining, public readonly string $requested)
    {
        parent::__construct(sprintf(
            'Quantité reçue trop élevée pour « %s » : %s restant(s), %s saisi(s).',
            $productName,
            $remaining,
            $requested,
        ));
    }
}
