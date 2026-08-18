<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Un contexte suspendu refuse toute mutation (docs/implementation/LOT-001 §22.14).
 */
final class CommercialContextSuspendedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce contexte commercial est suspendu ; aucune opération n’est possible.');
    }
}
