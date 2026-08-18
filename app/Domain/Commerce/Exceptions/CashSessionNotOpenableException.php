<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

final class CashSessionNotOpenableException extends RuntimeException
{
    public static function registerSuspended(): self
    {
        return new self('Cette caisse est suspendue ; elle ne peut pas ouvrir de nouvelle session.');
    }

    public static function registerAlreadyOpen(): self
    {
        return new self('Cette caisse a déjà une session ouverte.');
    }

    public static function actorAlreadyHasOpenSession(): self
    {
        return new self('Vous avez déjà une session de caisse ouverte dans ce contexte.');
    }

    public static function invalidOpeningAmount(): self
    {
        return new self('Le fonds de départ doit être un montant entier positif ou nul.');
    }
}
