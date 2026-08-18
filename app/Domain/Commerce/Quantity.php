<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use InvalidArgumentException;

/**
 * Arithmétique de quantité ET de montant à précision fixe (3 décimales), sans dépendre de
 * l'extension bcmath (pas garantie disponible sur tout environnement d'hébergement) et sans
 * jamais passer par un float — docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §2 :
 * « Montants monétaires : entiers, jamais float. »
 *
 * Toute quantité décimale ("1.250") est convertie en entier de milli-unités (1250) par lecture
 * de chaînes de caractères — jamais par une multiplication flottante — avant tout calcul. Le
 * calcul d'un montant de ligne (prix unitaire entier × quantité) reste lui aussi entier de bout
 * en bout, avec une règle d'arrondi explicite et centralisée ici (arrondi au plus proche, la
 * moitié s'éloignant de zéro).
 */
final class Quantity
{
    public const SCALE = 1000;

    public static function add(string $a, string $b): string
    {
        return self::fromScaledInteger(self::toScaledInteger($a) + self::toScaledInteger($b));
    }

    public static function subtract(string $a, string $b): string
    {
        return self::fromScaledInteger(self::toScaledInteger($a) - self::toScaledInteger($b));
    }

    public static function compare(string $a, string $b): int
    {
        return self::toScaledInteger($a) <=> self::toScaledInteger($b);
    }

    public static function isPositive(string $a): bool
    {
        return self::toScaledInteger($a) > 0;
    }

    /**
     * Montant XOF pour une ligne : prix unitaire entier × quantité, arrondi à l'entier le plus
     * proche. Seul point d'entrée pour ce calcul — ne pas le disperser ailleurs.
     */
    public static function moneyForUnitPrice(int $unitPriceXof, string $quantity): int
    {
        $scaledTotal = $unitPriceXof * self::toScaledInteger($quantity);

        return self::roundScaledToInt($scaledTotal);
    }

    /**
     * Conversion déterministe chaîne → entier de milli-unités, sans passer par un float. Accepte
     * un signe optionnel et jusqu'à 3 décimales (précision de `decimal(14,3)`) ; au-delà de 3
     * décimales, la valeur est tronquée (jamais arrondie en cachette, la précision de stockage
     * ne conserve de toute façon que 3 décimales).
     */
    public static function toScaledInteger(string $value): int
    {
        $value = trim($value);

        $negative = false;
        if (str_starts_with($value, '-')) {
            $negative = true;
            $value = substr($value, 1);
        } elseif (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        if ($value === '' || ! preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Quantité invalide : \"{$value}\".");
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, 3), 3, '0');

        $scaled = ((int) $whole) * self::SCALE + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }

    public static function fromScaledInteger(int $scaled): string
    {
        $negative = $scaled < 0;
        $scaled = abs($scaled);

        $whole = intdiv($scaled, self::SCALE);
        $fraction = $scaled % self::SCALE;

        return ($negative && $scaled !== 0 ? '-' : '').$whole.'.'.str_pad((string) $fraction, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Arrondi entier « au plus proche, la moitié s'éloignant de zéro » d'une valeur exprimée en
     * milli-unités vers une unité entière — sans division flottante.
     */
    private static function roundScaledToInt(int $scaledValue): int
    {
        $negative = $scaledValue < 0;
        $scaledValue = abs($scaledValue);

        $rounded = intdiv($scaledValue + intdiv(self::SCALE, 2), self::SCALE);

        return $negative ? -$rounded : $rounded;
    }
}
