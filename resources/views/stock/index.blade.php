{{-- Stock minimal (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §10). --}}
<x-layout title="Stock">
    <h1 class="gp-display">Stock</h1>

    @if(session('status'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-forest);color:var(--gp-forest);font-size:14px">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:14px">{{ $errors->first() }}</div>
    @endif

    @if($products->isEmpty())
        <x-empty-state title="Aucun produit suivi en stock" body="Les services et les produits qui ne suivent pas le stock n'apparaissent pas ici." />
    @else
        <div class="gp-card" style="padding:0;overflow:hidden">
            @foreach($products as $product)
                @php($quantity = (float) optional($product->stockBalance)->quantity)
                @php($lowStock = $product->reorder_threshold !== null && $product->stockBalance !== null && \App\Domain\Commerce\Quantity::compare((string) $product->stockBalance->quantity, (string) $product->reorder_threshold) <= 0)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 20px;border-bottom:1px solid var(--gp-line-inner);flex-wrap:wrap">
                    <strong style="font-size:14px;color:var(--gp-ink)">{{ $product->name }}</strong>
                    <span class="gp-status-pill {{ $quantity > 0 ? 'gp-status-pill--active' : 'gp-status-pill--suspended' }} gp-tabular">
                        {{ rtrim(rtrim((string) $quantity, '0'), '.') ?: '0' }} {{ $product->unit_label }}
                    </span>
                    @if($lowStock)
                        <span class="gp-status-pill gp-status-pill--pending">Stock faible</span>
                        @if($canPreparePurchase)
                            <a href="{{ route('purchases.create', ['produit' => $product->id]) }}" class="gp-btn gp-btn--quiet gp-btn--sm">Préparer un achat</a>
                        @endif
                    @endif
                    @if($canAdjustStock)
                        <details style="width:100%">
                            <summary style="cursor:pointer;font-size:13px;color:var(--gp-copper);font-weight:600;list-style:none">Ajuster ⌄</summary>
                            <form method="POST" action="{{ route('stock.adjust') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:10px">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <label class="gp-field" style="min-width:120px"><span>Quantité</span><input type="number" name="quantity" step="0.001" min="0.001" required></label>
                                <label class="gp-field" style="min-width:160px">
                                    <span>Mouvement</span>
                                    <select name="direction">
                                        <option value="IN">Entrée (réception)</option>
                                        <option value="ADJUSTMENT">Correction</option>
                                    </select>
                                </label>
                                <label class="gp-field" style="flex:1;min-width:160px"><span>Raison (facultative)</span><input type="text" name="reason" maxlength="200"></label>
                                <button type="submit" class="gp-btn gp-btn--quiet gp-btn--sm">Enregistrer</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-layout>
