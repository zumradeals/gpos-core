{{-- Choisir/créer un fournisseur, première étape du parcours (docs/implementation/LOT-002-
     PURCHASING-SUPPLY.md §22). --}}
<x-layout title="Nouvel achat">
    <a href="{{ route('purchases.hub') }}" class="gp-meta">← Acheter</a>
    <h1 class="gp-display" style="margin-top:6px">Choisir un fournisseur</h1>

    @if($suppliers->isEmpty())
        <x-empty-state title="Aucun fournisseur pour le moment" body="Ajoutez d'abord un fournisseur pour préparer une commande.">
            <a href="{{ route('suppliers.index') }}" class="gp-btn gp-btn--quiet gp-btn--sm">Gérer mes fournisseurs →</a>
        </x-empty-state>
    @else
        <form method="POST" action="{{ route('purchases.store') }}" class="gp-form">
            @csrf
            @if($productId)
                <input type="hidden" name="produit" value="{{ $productId }}">
            @endif
            <div class="gp-card" style="padding:0;overflow:hidden">
                @foreach($suppliers as $supplier)
                    <label style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--gp-line-inner);cursor:pointer">
                        <input type="radio" name="supplier_id" value="{{ $supplier->id }}" required @checked($loop->first)>
                        <span>
                            <strong style="font-size:14px;color:var(--gp-ink)">{{ $supplier->display_name }}</strong>
                            @if($supplier->contact_name)
                                <span class="gp-hint" style="display:block">{{ $supplier->contact_name }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="gp-btn gp-btn--primary" style="align-self:flex-start">Continuer</button>
        </form>
    @endif
</x-layout>
