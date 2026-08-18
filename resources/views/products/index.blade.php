{{-- Catalogue minimal (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §9). --}}
<x-layout title="Produits">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <h1 class="gp-display">Produits</h1>
    </div>

    @if(session('status'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-forest);color:var(--gp-forest);font-size:14px">{{ session('status') }}</div>
    @endif

    <form method="GET" class="gp-search">
        <input type="search" name="q" value="{{ $query }}" placeholder="Rechercher un produit…">
    </form>

    @if($canManageCatalog)
        <details class="gp-card" style="padding:0">
            <summary style="cursor:pointer;padding:16px 20px;font-weight:600;color:var(--gp-petrol);list-style:none">+ Ajouter un produit</summary>
            <form method="POST" action="{{ route('products.store') }}" class="gp-form" style="padding:0 20px 20px">
                @csrf
                <label class="gp-field"><span>Nom</span><input type="text" name="name" required maxlength="160" value="{{ old('name') }}"></label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
                    <label class="gp-field"><span>Prix de vente (XOF)</span><input type="number" name="sale_price_xof" min="0" step="1" required value="{{ old('sale_price_xof') }}"></label>
                    <label class="gp-field"><span>Unité</span><input type="text" name="unit_label" placeholder="unité, kg, sac…" value="{{ old('unit_label', 'unité') }}"></label>
                    <label class="gp-field">
                        <span>Type</span>
                        <select name="kind">
                            <option value="PRODUCT" @selected(old('kind', 'PRODUCT') === 'PRODUCT')>Produit (stock suivi)</option>
                            <option value="SERVICE" @selected(old('kind') === 'SERVICE')>Service (sans stock)</option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="gp-btn gp-btn--primary" style="align-self:flex-start">Ajouter au catalogue</button>
            </form>
        </details>
    @endif

    @if($products->isEmpty())
        <x-empty-state title="Aucun produit pour le moment" :body="$canManageCatalog ? 'Ajoutez votre premier produit ci-dessus.' : 'Aucun produit n’a encore été ajouté à ce catalogue.'" />
    @else
        <div class="gp-card" style="padding:0;overflow:hidden">
            @foreach($products as $product)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;border-bottom:1px solid var(--gp-line-inner)">
                    <div>
                        <strong style="font-size:14px;color:var(--gp-ink)">{{ $product->name }}</strong>
                        <div class="gp-hint">{{ $product->kind === 'SERVICE' ? 'Service' : 'Produit' }} · {{ $product->unit_label }}</div>
                    </div>
                    @if($product->track_stock)
                        <span class="gp-status-pill {{ (float) optional($product->stockBalance)->quantity > 0 ? 'gp-status-pill--active' : 'gp-status-pill--suspended' }}">
                            {{ rtrim(rtrim((string) optional($product->stockBalance)->quantity, '0'), '.') ?: '0' }} en stock
                        </span>
                    @endif
                    <span class="gp-money gp-tabular">{{ number_format($product->sale_price_xof, 0, ',', ' ') }} F</span>
                </div>
            @endforeach
        </div>
    @endif
</x-layout>
