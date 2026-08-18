<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Commerce\StockAdjuster;
use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stock minimal (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §10) : projection
 * courante + ajustement explicite, jamais un nombre modifié sans mouvement source.
 */
final class StockController extends Controller
{
    public function index(): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        $context = $actor->activeContext();

        $products = Product::query()
            ->where('context_id', $context->id)
            ->where('track_stock', true)
            ->where('active', true)
            ->with('stockBalance')
            ->orderBy('name')
            ->get();

        return view('stock.index', [
            'products' => $products,
            'canAdjustStock' => $actor->can(CommercialPermission::ADJUST_STOCK),
            'canPreparePurchase' => $actor->can(CommercialPermission::MANAGE_PURCHASES),
        ]);
    }

    public function adjust(Request $request, StockAdjuster $adjuster): RedirectResponse
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        abort_unless($actor->can(CommercialPermission::ADJUST_STOCK), 403);

        $data = $request->validate([
            'product_id' => ['required', 'uuid'],
            'direction' => ['required', Rule::in([StockMovement::DIRECTION_IN, StockMovement::DIRECTION_ADJUSTMENT])],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $product = Product::query()->where('context_id', $actor->activeContext()->id)->findOrFail($data['product_id']);

        $adjuster->adjust($actor->activeContext(), $actor->identity, $product, $data['direction'], (string) $data['quantity'], $data['reason'] ?? null);

        return redirect()->route('stock.index')->with('status', "Stock de « {$product->name} » mis à jour.");
    }
}
