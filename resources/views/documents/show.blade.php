{{--
    Document commercial — lit uniquement le snapshot figé au moment de l'émission, jamais l'état
    courant du produit/fournisseur (docs/architecture/SATELLITE-CONTRACT.md §13). Trois types
    partagent ce même gabarit : RECEIPT (vente), PURCHASE_ORDER (bon de commande), GOODS_RECEIPT
    (bon de réception) — docs/implementation/LOT-002-PURCHASING-SUPPLY.md §18.
--}}
@php($snapshot = $document->snapshot)
@php($titles = ['RECEIPT' => 'Reçu', 'PURCHASE_ORDER' => 'Bon de commande', 'GOODS_RECEIPT' => 'Bon de réception'])
@php($title = $titles[$document->document_type] ?? 'Document')
@php($quantityKey = $document->document_type === 'RECEIPT' ? 'quantity' : ($document->document_type === 'PURCHASE_ORDER' ? 'ordered_quantity' : 'quantity'))
@php($costKey = $document->document_type === 'RECEIPT' ? 'unit_price_xof' : 'unit_cost_xof')
<x-layout title="{{ $title }} {{ $document->number }}">
    <div class="gp-no-print" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
        <a href="{{ route('documents.index') }}" class="gp-meta">← Tous les documents</a>
        <button type="button" onclick="window.print()" class="gp-btn gp-btn--quiet gp-btn--sm">Imprimer</button>
    </div>

    <div class="gp-receipt">
        <div style="text-align:center">
            <div class="gp-label">{{ $snapshot['context_display_name'] ?? '' }}</div>
            <h1 class="gp-display" style="margin-top:6px">{{ $title }}</h1>
            <div class="gp-meta gp-tabular">{{ $document->number }} · {{ $snapshot['reference'] ?? $snapshot['purchase_order_reference'] ?? '' }}</div>
            @if($document->document_type !== 'RECEIPT')
                <div class="gp-meta">{{ $snapshot['supplier_display_name'] ?? '' }}</div>
            @endif
            @if($document->document_type === 'GOODS_RECEIPT')
                <div class="gp-meta">Commande {{ $snapshot['purchase_order_reference'] ?? '' }}</div>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:8px">
            @foreach($snapshot['lines'] ?? [] as $line)
                <div class="gp-receipt__line">
                    <span>{{ $line['product_name'] }} × {{ rtrim(rtrim((string) ($line[$quantityKey] ?? $line['quantity'] ?? '0'), '0'), '.') ?: '0' }}</span>
                    <span class="gp-tabular">{{ number_format($line['line_total_xof'], 0, ',', ' ') }} F</span>
                </div>
            @endforeach
        </div>

        <div class="gp-receipt__line gp-receipt__line--total">
            <span>
                @if($document->document_type === 'RECEIPT')
                    Total payé — {{ $snapshot['payment_method'] ?? 'CASH' }}
                @elseif($document->document_type === 'PURCHASE_ORDER')
                    Total commandé
                @else
                    Total reçu
                @endif
            </span>
            <span class="gp-tabular">{{ number_format($snapshot['total_xof'] ?? 0, 0, ',', ' ') }} F</span>
        </div>

        <div class="gp-meta" style="text-align:center">Émis le {{ $document->issued_at->format('d/m/Y à H:i') }}</div>
    </div>
</x-layout>
