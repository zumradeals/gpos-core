<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/**
 * Arithmétique de quantité à précision fixe (3 décimales), sans dépendre de l'extension bcmath
 * (pas garantie disponible sur tout environnement d'hébergement). Les quantités converties en
 * « milli-unités » entières évitent toute dérive d'arrondi flottant sur les volumes réalistes
 * d'un commerce de détail.
 */
final class Quantity
{
    private const SCALE = 1000;

    public static function add(string $a, string $b): string
    {
        return self::format(self::toMilli($a) + self::toMilli($b));
    }

    public static function subtract(string $a, string $b): string
    {
        return self::format(self::toMilli($a) - self::toMilli($b));
    }

    public static function compare(string $a, string $b): int
    {
        return self::toMilli($a) <=> self::toMilli($b);
    }

    public static function isPositive(string $a): bool
    {
        return self::toMilli($a) > 0;
    }

    private static function toMilli(string $value): int
    {
        return (int) round(((float) $value) * self::SCALE);
    }

    private static function format(int $milli): string
    {
        return number_format($milli / self::SCALE, 3, '.', '');
    }
}
