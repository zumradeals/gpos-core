<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Identity\CoreIdentityReference;
use App\Models\CommercialContext;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Fournisseurs locaux/manuels uniquement pour LOT-002 (docs/implementation/LOT-002-PURCHASING-
 * SUPPLY.md §7.1) — external_origin_* restent toujours null ici.
 */
final class SupplierManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(CommercialContext $context, CoreIdentityReference $actor, array $data): Supplier
    {
        return DB::transaction(function () use ($context, $actor, $data): Supplier {
            $supplier = Supplier::query()->create([
                'context_id' => $context->id,
                'display_name' => $data['display_name'],
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'external_origin_type' => null,
                'external_origin_reference' => null,
                'active' => true,
            ]);

            $this->audit->record(
                $context,
                $actor,
                'supplier.created',
                'Supplier',
                (string) $supplier->id,
                null,
                $supplier->only(['display_name', 'contact_name', 'phone', 'email']),
            );

            return $supplier;
        });
    }
}
