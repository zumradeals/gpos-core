<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\ProductCatalog;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catalogue minimal (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §9) : liste simple,
 * recherche immédiate, ajout sans formulaire intimidant, état vide clair.
 */
final class ProductController extends Controller
{
    public function index(Request $request): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        $context = $actor->activeContext();

        $products = Product::query()
            ->where('context_id', $context->id)
            ->where('active', true)
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%'.$request->query('q').'%'))
            ->with('stockBalance')
            ->orderBy('name')
            ->get();

        return view('products.index', [
            'products' => $products,
            'canManageCatalog' => $actor->can(CommercialPermission::MANAGE_CATALOG),
            'query' => (string) $request->query('q', ''),
        ]);
    }

    public function store(Request $request, ProductCatalog $catalog): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($actor->can(CommercialPermission::MANAGE_CATALOG), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'in:PRODUCT,SERVICE'],
            'sale_price_xof' => ['required', 'integer', 'min:0'],
            'unit_label' => ['nullable', 'string', 'max:40'],
            'sku' => ['nullable', 'string', 'max:60'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'track_stock' => ['nullable', 'boolean'],
        ]);

        $product = $catalog->create($actor->activeContext(), $actor->identity, $data);

        return redirect()->route('products.index')->with('status', "« {$product->name} » a été ajouté au catalogue.");
    }
}
