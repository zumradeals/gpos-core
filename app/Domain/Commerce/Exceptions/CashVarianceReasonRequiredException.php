<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Un écart ne disparaît jamais : s'il est non nul, une justification réelle est obligatoire
 * (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §4, §17.2, §24). Le motif explique
 * l'écart, il ne le supprime pas.
 */
final class CashVarianceReasonRequiredException extends RuntimeException
{
    public function __construct(public readonly int $varianceXof)
    {
        parent::__construct('Expliquez cet écart avant de clôturer.');
    }
}
