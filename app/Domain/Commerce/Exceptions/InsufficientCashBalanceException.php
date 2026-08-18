<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Une sortie CASH ne peut jamais faire devenir le solde attendu négatif (docs/implementation/
 * LOT-003-CASH-REGISTER-CLOSING.md §4, §10.2, §12).
 */
final class InsufficientCashBalanceException extends RuntimeException
{
    public function __construct(public readonly int $availableXof, public readonly int $requestedXof)
    {
        parent::__construct(sprintf(
            'Solde de caisse insuffisant : %d F disponible(s), %d F demandé(s).',
            $availableXof,
            $requestedXof,
        ));
    }
}
