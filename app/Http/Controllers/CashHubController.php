<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\CashBalanceCalculator;
use App\Application\Commerce\CashSessionResolver;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use App\Models\CashRegister;
use Illuminate\View\View;

/**
 * Hub Caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §22) : aucune caisse, caisse
 * fermée, ou session ouverte — trois écrans honnêtes, jamais un choix silencieux entre plusieurs
 * caisses.
 */
final class CashHubController extends Controller
{
    public function index(CashSessionResolver $resolver, CashBalanceCalculator $calculator): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        $context = $actor->activeContext();

        $openSession = $resolver->findOpenSessionForActor($context, $actor->identity);

        if ($openSession !== null) {
            $openSession->load('cashRegister');

            return view('cash.session', [
                'session' => $openSession,
                'register' => $openSession->cashRegister,
                'expected' => $calculator->expected($openSession),
                'movements' => $openSession->movements()->orderByDesc('occurred_at')->limit(20)->get(),
                'canClose' => $actor->can(CommercialPermission::CLOSE_CASH),
                'canOperateCash' => $actor->can(CommercialPermission::OPERATE_CASH),
            ]);
        }

        $registers = CashRegister::query()
            ->where('context_id', $context->id)
            ->where('status', CashRegister::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        return view('cash.setup', [
            'registers' => $registers,
            'canManageCash' => $actor->can(CommercialPermission::MANAGE_CASH),
            'canOperateCash' => $actor->can(CommercialPermission::OPERATE_CASH),
        ]);
    }
}
