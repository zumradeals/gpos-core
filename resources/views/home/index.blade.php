{{-- Accueil — jamais un dashboard KPI (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §17). --}}
<x-layout title="Accueil">
    <div class="gp-deep">
        <div class="gp-label" style="color:var(--gp-on-deep-muted)">{{ $context->display_name }}</div>
        <h1 class="gp-display gp-display--lg" style="margin-top:6px">Bonjour.</h1>
        <p class="gp-body" style="color:var(--gp-on-deep-text);margin-top:6px;max-width:56ch">
            @if(empty($todo))
                Rien ne réclame une décision maintenant.
            @else
                {{ count($todo) === 1 ? 'Une chose mérite votre attention.' : count($todo).' choses méritent votre attention.' }}
            @endif
        </p>
    </div>

    @if(! empty($todo))
        <div>
            <div class="gp-label" style="margin-bottom:10px">À faire maintenant</div>
            <div style="display:flex;flex-direction:column;gap:8px">
                @foreach($todo as $item)
                    <a href="{{ $item['href'] }}" class="gp-action-card gp-action-card--primary">
                        <strong>{{ $item['label'] }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <div class="gp-label" style="margin-bottom:10px">Actions rapides</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
            @if($canSell)
                <a href="{{ route('sell.show') }}" class="gp-action-card"><strong>Vendre</strong><span class="gp-hint">Ouvrir le parcours de vente</span></a>
            @endif
            @if($canManagePurchases)
                <a href="{{ route('purchases.create') }}" class="gp-action-card"><strong>Nouvel achat</strong><span class="gp-hint">Commander auprès d'un fournisseur</span></a>
            @endif
            <a href="{{ route('products.index') }}" class="gp-action-card"><strong>Ajouter un produit</strong><span class="gp-hint">Compléter le catalogue</span></a>
            @if($canViewStock)
                <a href="{{ route('stock.index') }}" class="gp-action-card"><strong>Stock</strong><span class="gp-hint">Voir les soldes courants</span></a>
            @endif
        </div>
    </div>

    @if($canViewDocuments)
        <div>
            <div class="gp-label" style="margin-bottom:10px">Activité récente</div>
            @if($recentSales->isEmpty())
                <x-empty-state title="Rien ne s'est encore passé ici" body="Dès qu'une vente sera confirmée, elle apparaîtra ici." />
            @else
                <div class="gp-card" style="padding:0;overflow:hidden">
                    @foreach($recentSales as $sale)
                        <a href="{{ route('documents.show', $sale->document) }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;color:inherit;border-bottom:1px solid var(--gp-line-inner)">
                            <span class="gp-meta gp-tabular">{{ $sale->reference }}</span>
                            <span class="gp-money gp-tabular">{{ number_format($sale->total_xof, 0, ',', ' ') }} F</span>
                            <span class="gp-meta">{{ $sale->confirmed_at?->diffForHumans() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-layout>
