<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Commerce;

use App\Domain\Commerce\Quantity;
use PHPUnit\Framework\TestCase;

/**
 * Revue LOT-001 — BLOQUEUR 2 : aucun float dans le calcul monétaire. Quantity convertit toute
 * quantité décimale en entier de milli-unités par lecture de chaîne (jamais par une multiplication
 * flottante), et centralise la seule règle d'arrondi du montant de ligne.
 */
final class QuantityTest extends TestCase
{
    public function test_three_units_at_1200_xof_gives_3600_xof(): void
    {
        self::assertSame(3600, Quantity::moneyForUnitPrice(1200, '3'));
    }

    public function test_fractional_quantity_times_integer_price_is_deterministic(): void
    {
        // 1.250 unités à 1200 XOF/unité = 1500.000 XOF, sans reste.
        self::assertSame(1500, Quantity::moneyForUnitPrice(1200, '1.250'));

        // 1.250 unités à 999 XOF/unité = 1248.75 XOF -> arrondi au plus proche = 1249.
        self::assertSame(1249, Quantity::moneyForUnitPrice(999, '1.250'));
    }

    public function test_repeated_computation_never_drifts(): void
    {
        // Une opération qu'un calcul flottant ferait dériver (0.1 + 0.2 != 0.3 en IEEE 754)
        // répétée de nombreuses fois doit rester parfaitement stable en entier de milli-unités.
        $quantity = '0.000';
        for ($i = 0; $i < 1000; $i++) {
            $quantity = Quantity::add($quantity, '0.001');
        }

        self::assertSame('1.000', $quantity);
        self::assertSame(1000, Quantity::moneyForUnitPrice(1000, $quantity));
    }

    public function test_to_scaled_integer_reads_the_decimal_string_without_float_conversion(): void
    {
        self::assertSame(1250, Quantity::toScaledInteger('1.250'));
        self::assertSame(1250, Quantity::toScaledInteger('1.25'));
        self::assertSame(-1250, Quantity::toScaledInteger('-1.25'));
        self::assertSame(3000, Quantity::toScaledInteger('3'));
    }

    public function test_add_and_subtract_round_trip_exactly(): void
    {
        self::assertSame('4.250', Quantity::add('1.250', '3'));
        self::assertSame('0.750', Quantity::subtract('1.250', '0.500'));
    }
}
