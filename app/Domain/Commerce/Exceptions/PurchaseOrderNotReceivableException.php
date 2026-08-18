<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

final class PurchaseOrderNotReceivableException extends RuntimeException
{
    public static function wrongStatus(string $status): self
    {
        return new self("Cette commande ne peut pas être réceptionnée dans son état actuel ({$status}).");
    }

    public static function nothingReceived(): self
    {
        return new self('Indiquez au moins une quantité reçue avant de confirmer.');
    }
}
