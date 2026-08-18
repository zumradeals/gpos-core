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

    public const VIEW_PURCHASES = 'VIEW_PURCHASES';

    public const MANAGE_PURCHASES = 'MANAGE_PURCHASES';

    public const RECEIVE_PURCHASES = 'RECEIVE_PURCHASES';

    public const PAY_PURCHASES = 'PAY_PURCHASES';

    public const VIEW_CASH = 'VIEW_CASH';

    public const OPERATE_CASH = 'OPERATE_CASH';

    public const CLOSE_CASH = 'CLOSE_CASH';

    public const MANAGE_CASH = 'MANAGE_CASH';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::SELL, self::MANAGE_CATALOG, self::VIEW_STOCK, self::ADJUST_STOCK,
            self::VIEW_DOCUMENTS, self::VIEW_AUDIT, self::VIEW_PURCHASES, self::MANAGE_PURCHASES,
            self::RECEIVE_PURCHASES, self::PAY_PURCHASES, self::VIEW_CASH, self::OPERATE_CASH,
            self::CLOSE_CASH, self::MANAGE_CASH,
        ];
    }
}
