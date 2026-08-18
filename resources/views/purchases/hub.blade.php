{{-- Hub Acheter (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §21). --}}
<x-layout title="Acheter">
    @php($statusLabels = ['DRAFT' => 'Brouillon', 'ORDERED' => 'Commandée', 'PARTIALLY_RECEIVED' => 'Réception partielle', 'RECEIVED' => 'Reçue', 'CANCELLED' => 'Annulée'])
    @php($statusPill = ['DRAFT' => 'draft', 'ORDERED' => 'pending', 'PARTIALLY_RECEIVED' => 'pending', 'RECEIVED' => 'confirmed', 'CANCELLED' => 'cancelled'])

    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <h1 class="gp-display">Acheter</h1>
        @if($canManagePurchases)
            <a href="{{ route('purchases.create') }}" class="gp-btn gp-btn--primary">Nouvel achat</a>
        @endif
    </div>

    @if(session('status'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-forest);color:var(--gp-forest);font-size:14px">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:14px">{{ session('error') }}</div>
    @endif

    @if($toReceive->isNotEmpty())
        <div>
            <div class="gp-label" style="margin-bottom:10px">À réceptionner</div>
            <div style="display:flex;flex-direction:column;gap:8px">
                @foreach($toReceive as $order)
                    <a href="{{ route('purchases.receive', $order) }}" class="gp-action-card gp-action-card--primary">
                        <strong>{{ $order->reference }} — {{ $order->supplier_display_name_snapshot }}</strong>
                        <span class="gp-hint">{{ $statusLabels[$order->status] }} · {{ number_format($order->total_xof, 0, ',', ' ') }} F</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if($canViewPurchases)
        <div>
            <div class="gp-label" style="margin-bottom:10px">Commandes récentes</div>

            @if($recentOrders->isEmpty() && $toReceive->isEmpty())
                <x-empty-state title="Aucun achat pour le moment" body="Ajoutez un fournisseur puis préparez votre première commande.">
                    @if($canManagePurchases)
                        <a href="{{ route('purchases.create') }}" class="gp-btn gp-btn--quiet gp-btn--sm">Nouvel achat →</a>
                    @endif
                </x-empty-state>
            @elseif($recentOrders->isEmpty())
                <x-empty-state title="Aucune commande confirmée pour le moment" body="Les commandes confirmées apparaîtront ici." />
            @else
                <div class="gp-card" style="padding:0;overflow:hidden">
                    @foreach($recentOrders as $order)
                        <a href="{{ route('purchases.show', $order) }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;color:inherit;border-bottom:1px solid var(--gp-line-inner);flex-wrap:wrap">
                            <span class="gp-meta gp-tabular">{{ $order->reference }}</span>
                            <span style="flex:1;min-width:120px">{{ $order->supplier_display_name_snapshot }}</span>
                            <span class="gp-status-pill gp-status-pill--{{ $statusPill[$order->status] }}">{{ $statusLabels[$order->status] }}</span>
                            <span class="gp-money gp-tabular">{{ number_format($order->total_xof, 0, ',', ' ') }} F</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <a href="{{ route('suppliers.index') }}" class="gp-meta">Gérer mes fournisseurs →</a>
</x-layout>
