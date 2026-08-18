{{-- Ma caisse — session ouverte (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §22.3). --}}
@php($typeLabels = ['OPENING_FLOAT' => 'Fonds de départ', 'SALE_PAYMENT' => 'Vente', 'PURCHASE_PAYMENT' => 'Achat fournisseur', 'MANUAL_IN' => 'Entrée manuelle', 'MANUAL_OUT' => 'Sortie manuelle'])
<x-layout title="Ma caisse">
    @if(session('status'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-forest);color:var(--gp-forest);font-size:14px">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:14px">{{ session('error') }}</div>
    @endif

    <div class="gp-deep">
        <div class="gp-label" style="color:var(--gp-on-deep-muted)">Ma caisse — {{ $register->name }}</div>
        <h1 class="gp-display gp-display--lg" style="margin-top:6px;color:var(--gp-on-deep-title)">Ouverte depuis {{ $session->opened_at->format('H:i') }}</h1>
        <p class="gp-body" style="color:var(--gp-on-deep-text);margin-top:10px">Espèces attendues</p>
        <strong class="gp-tabular" style="font-family:var(--gp-font-display);font-size:36px;color:var(--gp-on-deep-title)">{{ number_format($expected, 0, ',', ' ') }} F</strong>
    </div>

    @if($canOperateCash)
        <div class="gp-card">
            <div class="gp-label" style="margin-bottom:10px">Ajouter un mouvement</div>
            <form method="POST" action="{{ route('cash.movements.store') }}" class="gp-form">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <label class="gp-field">
                        <span>Type</span>
                        <select name="direction">
                            <option value="IN">Entrée</option>
                            <option value="OUT">Sortie</option>
                        </select>
                    </label>
                    <label class="gp-field"><span>Montant (XOF)</span><input type="number" name="amount_xof" step="1" min="1" required></label>
                </div>
                <label class="gp-field"><span>Motif</span><input type="text" name="reason" maxlength="255" required placeholder="Pourquoi ce mouvement ?"></label>
                <button type="submit" class="gp-btn gp-btn--quiet gp-btn--sm" style="align-self:flex-start">Enregistrer</button>
            </form>
        </div>
    @endif

    <div>
        <div class="gp-label" style="margin-bottom:10px">Mouvements récents</div>
        @if($movements->isEmpty())
            <x-empty-state title="Aucun mouvement pour le moment" body="Les ventes, achats et mouvements manuels apparaîtront ici." />
        @else
            <div class="gp-card" style="padding:0;overflow:hidden">
                @foreach($movements as $movement)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;border-bottom:1px solid var(--gp-line-inner)">
                        <span>{{ $typeLabels[$movement->movement_type] ?? $movement->movement_type }}</span>
                        <span class="gp-meta">{{ $movement->reason }}</span>
                        <span class="gp-money gp-tabular" style="color:{{ $movement->direction === 'IN' ? 'var(--gp-forest)' : 'var(--gp-error)' }}">
                            {{ $movement->direction === 'IN' ? '+' : '−' }}{{ number_format($movement->amount_xof, 0, ',', ' ') }} F
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($canClose)
        <a href="{{ route('cash.closure.create') }}" class="gp-btn gp-btn--primary gp-btn--block">Clôturer ma caisse</a>
    @endif
</x-layout>
