<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\CommercialContext;

/**
 * L'acteur courant pour la requête en cours : une référence Core stable, jamais un modèle
 * Eloquent User local (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.2).
 *
 * Le contexte commercial actif et les permissions qu'il y détient sont résolus séparément
 * (ResolveActiveCommercialContext) car un même humain peut agir dans plusieurs contextes avec des
 * permissions différentes (docs/architecture/SATELLITE-CONTRACT.md §4).
 */
final class CurrentActor
{
    /** @var list<string> */
    private array $permissions = [];

    private ?CommercialContext $activeContext = null;

    public function __construct(
        public readonly CoreIdentityReference $identity,
    ) {}

    public function withActiveContext(CommercialContext $context, array $permissions): self
    {
        $actor = new self($this->identity);
        $actor->activeContext = $context;
        $actor->permissions = array_values(array_unique($permissions));

        return $actor;
    }

    public function activeContext(): ?CommercialContext
    {
        return $this->activeContext;
    }

    public function hasActiveContext(): bool
    {
        return $this->activeContext !== null;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions;
    }
}
