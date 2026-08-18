<?php

declare(strict_types=1);

namespace App\Domain\Identity;

/**
 * Référence stable vers une identité GAMAD Core. G-POS ne possède jamais l'identité elle-même
 * (docs/architecture/SATELLITE-CONTRACT.md §3) — cette valeur n'est qu'une référence opaque.
 */
final readonly class CoreIdentityReference
{
    public function __construct(
        public string $reference,
        public ?string $label = null,
    ) {}

    public function equals(self $other): bool
    {
        return $this->reference === $other->reference;
    }

    public function __toString(): string
    {
        return $this->reference;
    }
}
