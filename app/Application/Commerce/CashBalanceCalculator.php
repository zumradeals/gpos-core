<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Models\CashMovement;
use App\Models\CashSession;

/**
 * Solde attendu — toujours recalculé depuis le registre immuable, jamais stocké comme vérité
 * mutable pendant la session (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §12).
 *
 * attendu = somme(IN) - somme(OUT), en entier XOF, jamais un float.
 */
final class CashBalanceCalculator
{
    public function expected(CashSession $session): int
    {
        $totals = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->selectRaw('direction, SUM(amount_xof) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        $in = (int) ($totals[CashMovement::DIRECTION_IN] ?? 0);
        $out = (int) ($totals[CashMovement::DIRECTION_OUT] ?? 0);

        return $in - $out;
    }
}
