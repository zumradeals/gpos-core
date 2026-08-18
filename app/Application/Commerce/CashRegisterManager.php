<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Identity\CoreIdentityReference;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CommercialContext;
use Illuminate\Support\Facades\DB;

/**
 * Caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §8) : appartient à un seul
 * contexte, jamais supprimée physiquement via l'UX LOT-003.
 */
final class CashRegisterManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(CommercialContext $context, CoreIdentityReference $actor, array $data): CashRegister
    {
        return DB::transaction(function () use ($context, $actor, $data): CashRegister {
            $register = CashRegister::query()->create([
                'context_id' => $context->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'status' => CashRegister::STATUS_ACTIVE,
                'created_by_core_reference' => $actor->reference,
            ]);

            $this->audit->record(
                $context,
                $actor,
                'cash.register_created',
                'CashRegister',
                (string) $register->id,
                null,
                $register->only(['name', 'code']),
            );

            return $register;
        });
    }

    /**
     * Une caisse avec une session ouverte ne doit pas être suspendue silencieusement (§8.1).
     */
    public function suspend(CashRegister $register, CommercialContext $context, CoreIdentityReference $actor): CashRegister
    {
        abort_if($register->context_id !== $context->id, 403);

        return DB::transaction(function () use ($register, $context, $actor): CashRegister {
            /** @var CashRegister $locked */
            $locked = CashRegister::query()->whereKey($register->id)->lockForUpdate()->firstOrFail();

            $hasOpenSession = CashSession::query()
                ->where('cash_register_id', $locked->id)
                ->where('status', CashSession::STATUS_OPEN)
                ->exists();

            abort_if($hasOpenSession, 409, 'Cette caisse a une session ouverte ; elle ne peut pas être suspendue maintenant.');

            $before = $locked->status;
            $locked->update(['status' => CashRegister::STATUS_SUSPENDED]);

            $this->audit->record(
                $context,
                $actor,
                'cash.register_suspended',
                'CashRegister',
                (string) $locked->id,
                ['status' => $before],
                ['status' => CashRegister::STATUS_SUSPENDED],
            );

            return $locked->refresh();
        });
    }
}
