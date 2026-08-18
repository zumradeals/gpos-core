<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\SupplierManager;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fournisseurs locaux (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §7) : liste simple,
 * création sans formulaire intimidant, jamais de mention « vérifié » sans preuve réelle.
 */
final class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        $context = $actor->activeContext();

        $suppliers = Supplier::query()
            ->where('context_id', $context->id)
            ->where('active', true)
            ->when($request->filled('q'), fn ($q) => $q->where('display_name', 'ilike', '%'.$request->query('q').'%'))
            ->orderBy('display_name')
            ->get();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'canManagePurchases' => $actor->can(CommercialPermission::MANAGE_PURCHASES),
            'query' => (string) $request->query('q', ''),
        ]);
    }

    public function store(Request $request, SupplierManager $manager): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($actor->can(CommercialPermission::MANAGE_PURCHASES), 403);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $supplier = $manager->create($actor->activeContext(), $actor->identity, $data);

        return redirect()->route('suppliers.index')->with('status', "« {$supplier->display_name} » a été ajouté à vos fournisseurs.");
    }
}
