{{-- Fournisseurs locaux (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §7). --}}
<x-layout title="Fournisseurs">
    <a href="{{ route('purchases.hub') }}" class="gp-meta">← Acheter</a>
    <h1 class="gp-display" style="margin-top:6px">Fournisseurs</h1>

    @if(session('status'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-forest);color:var(--gp-forest);font-size:14px">{{ session('status') }}</div>
    @endif

    <form method="GET" class="gp-search">
        <input type="search" name="q" value="{{ $query }}" placeholder="Rechercher un fournisseur…">
    </form>

    @if($canManagePurchases)
        <details class="gp-card" style="padding:0">
            <summary style="cursor:pointer;padding:16px 20px;font-weight:600;color:var(--gp-petrol);list-style:none">+ Ajouter un fournisseur</summary>
            <form method="POST" action="{{ route('suppliers.store') }}" class="gp-form" style="padding:0 20px 20px">
                @csrf
                <label class="gp-field"><span>Nom</span><input type="text" name="display_name" required maxlength="160" value="{{ old('display_name') }}"></label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
                    <label class="gp-field"><span>Contact (facultatif)</span><input type="text" name="contact_name" maxlength="160" value="{{ old('contact_name') }}"></label>
                    <label class="gp-field"><span>Téléphone (facultatif)</span><input type="text" name="phone" maxlength="40" value="{{ old('phone') }}"></label>
                </div>
                <button type="submit" class="gp-btn gp-btn--primary" style="align-self:flex-start">Ajouter</button>
            </form>
        </details>
    @endif

    @if($suppliers->isEmpty())
        <x-empty-state title="Aucun fournisseur pour le moment" :body="$canManagePurchases ? 'Ajoutez votre premier fournisseur ci-dessus.' : 'Aucun fournisseur n’a encore été ajouté.'" />
    @else
        <div class="gp-card" style="padding:0;overflow:hidden">
            @foreach($suppliers as $supplier)
                <div style="padding:14px 20px;border-bottom:1px solid var(--gp-line-inner)">
                    <strong style="font-size:14px;color:var(--gp-ink)">{{ $supplier->display_name }}</strong>
                    @if($supplier->contact_name || $supplier->phone)
                        <div class="gp-hint">{{ $supplier->contact_name }}{{ $supplier->contact_name && $supplier->phone ? ' · ' : '' }}{{ $supplier->phone }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-layout>
