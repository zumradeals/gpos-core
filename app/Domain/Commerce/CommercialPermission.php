<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/**
 * Permissions commerciales locales à G-POS (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md
 * §8). Elles n'accordent jamais d'autorité ZUMRA ou GAMAD (docs/G-POS-DOCTRINE.md §10).
 */
final class CommercialPermission
{
    public const SELL = 'SELL';

    public const MANAGE_CATALOG = 'MANAGE_CATALOG';

    public const VIEW_STOCK = 'VIEW_STOCK';

    public const ADJUST_STOCK = 'ADJUST_STOCK';

    public const VIEW_DOCUMENTS = 'VIEW_DOCUMENTS';

    public const VIEW_AUDIT = 'VIEW_AUDIT';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::SELL, self::MANAGE_CATALOG, self::VIEW_STOCK, self::ADJUST_STOCK, self::VIEW_DOCUMENTS, self::VIEW_AUDIT];
    }
}
