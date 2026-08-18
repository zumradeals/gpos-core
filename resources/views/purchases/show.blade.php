{{-- Détail d'une commande d'achat confirmée (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §22-24). --}}
@php($statusLabels = ['DRAFT' => 'Brouillon', 'ORDERED' => 'Commandée', 'PARTIALLY_RECEIVED' => 'Réception partielle', 'RECEIVED' => 'Reçue', 'CANCELLED' => 'Annulée'])
@php($statusPill = ['DRAFT' => 'draft', 'ORDERED' => 'pending', 'PARTIALLY_RECEIVED' => 'pending', 'RECEIVED' => 'confirmed', 'CANCELLED' => 'cancelled'])
<x-layout title="{{ $order->reference }}">
    <a href="{{ route('purchases.hub') }}" class="gp-meta">← Acheter</a>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
            <h1 class="gp-display" style="margin-top:6px">{{ $order->reference }}</h1>
            <div class="gp-hint">{{ $order->supplier_display_name_snapshot }}</div>
        </div>
        <span class="gp-status-pill gp-status-pill--{{ $statusPill[$order->status] }}">{{ $statusLabels[$order->status] }}</span>
    </div>

    @if(session('status'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-forest);color:var(--gp-forest);font-size:14px">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:14px">{{ session('error') }}</div>
    @endif

    <div class="gp-card" style="padding:0;overflow:hidden">
        @foreach($order->lines as $line)
            @php($remaining = \App\Domain\Commerce\Quantity::subtract((string) $line->ordered_quantity, (string) $line->received_quantity))
            <div style="padding:14px 20px;border-bottom:1px solid var(--gp-line-inner);display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                    <strong style="font-size:14px;color:var(--gp-ink)">{{ $line->product_name_snapshot }}</strong>
                    <span class="gp-money gp-tabular">{{ number_format($line->line_total_xof, 0, ',', ' ') }} F</span>
                </div>
                <div style="display:flex;gap:16px;flex-wrap:wrap" class="gp-hint">
                    <span>Commandé <span class="gp-tabular">{{ rtrim(rtrim((string) $line->ordered_quantity, '0'), '.') ?: '0' }}</span></span>
                    <span>Reçu <span class="gp-tabular">{{ rtrim(rtrim((string) $line->received_quantity, '0'), '.') ?: '0' }}</span></span>
                    @if($order->status !== 'CANCELLED' && $order->status !== 'RECEIVED')
                        <span>Restant <span class="gp-tabular">{{ rtrim(rtrim($remaining, '0'), '.') ?: '0' }}</span></span>
                    @endif
                </div>
            </div>
        @endforeach
        <div style="padding:14px 20px;display:flex;align-items:baseline;justify-content:space-between">
            <span class="gp-meta">Total</span>
            <strong class="gp-tabular" style="font-family:var(--gp-font-display);font-size:24px;color:var(--gp-petrol)">{{ number_format($order->total_xof, 0, ',', ' ') }} F</strong>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($order->document)
            <a href="{{ route('documents.show', $order->document) }}" class="gp-btn gp-btn--quiet gp-btn--sm">Bon de commande</a>
        @endif
        @foreach($order->receipts as $receipt)
            @if($receipt->document)
                <a href="{{ route('documents.show', $receipt->document) }}" class="gp-btn gp-btn--quiet gp-btn--sm">Bon de réception {{ $receipt->reference }}</a>
            @endif
        @endforeach
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($canReceive && in_array($order->status, ['ORDERED', 'PARTIALLY_RECEIVED']))
            <a href="{{ route('purchases.receive', $order) }}" class="gp-btn gp-btn--primary">Réceptionner</a>
        @endif

        @if($canCancel)
            <form method="POST" action="{{ route('purchases.cancel', $order) }}" onsubmit="return confirm('Annuler cette commande ?')">
                @csrf
                <button type="submit" class="gp-btn gp-btn--quiet gp-btn--sm">Annuler la commande</button>
            </form>
        @endif
    </div>

    @if($order->status === 'RECEIVED')
        <div class="gp-card">
            <div class="gp-label" style="margin-bottom:10px">Paiement fournisseur</div>
            @if($order->payment)
                <p class="gp-body">Payé comptant · {{ $order->payment->paid_at->format('d/m/Y à H:i') }}</p>
            @elseif($order->total_xof === 0)
                <p class="gp-hint">Aucun montant à régler.</p>
            @elseif($canPay)
                <p class="gp-body">Montant : <strong class="gp-tabular">{{ number_format($order->total_xof, 0, ',', ' ') }} F CFA</strong></p>
                <form method="POST" action="{{ route('purchases.pay', $order) }}">
                    @csrf
                    <button type="submit" class="gp-btn gp-btn--primary">Marquer payé comptant</button>
                </form>
            @else
                <p class="gp-hint">En attente de paiement.</p>
            @endif
        </div>
    @endif
</x-layout>
