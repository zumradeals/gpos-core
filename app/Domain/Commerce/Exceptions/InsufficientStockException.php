<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Stock insuffisant sur au moins une ligne : refuse toute la confirmation, atomiquement
 * (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §11.2, §22.10).
 */
final class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly string $productName, public readonly string $available, public readonly string $requested)
    {
        parent::__construct(sprintf(
            'Stock insuffisant pour « %s » : %s disponible(s), %s demandé(s).',
            $productName,
            $available,
            $requested,
        ));
    }
}
