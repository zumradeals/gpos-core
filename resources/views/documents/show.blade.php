{{--
    Document commercial — lit uniquement le snapshot figé au moment de l'émission, jamais l'état
    courant du produit/fournisseur/caisse (docs/architecture/SATELLITE-CONTRACT.md §13). Quatre
    types partagent ce gabarit : RECEIPT (vente), PURCHASE_ORDER (bon de commande), GOODS_RECEIPT
    (bon de réception) — docs/implementation/LOT-002-PURCHASING-SUPPLY.md §18 — et CASH_CLOSURE
    (preuve de clôture de caisse) — docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §18.
--}}
@php($snapshot = $document->snapshot)
@php($titles = ['RECEIPT' => 'Reçu', 'PURCHASE_ORDER' => 'Bon de commande', 'GOODS_RECEIPT' => 'Bon de réception', 'CASH_CLOSURE' => 'Clôture de caisse'])
@php($title = $titles[$document->document_type] ?? 'Document')
@php($quantityKey = $document->document_type === 'RECEIPT' ? 'quantity' : ($document->document_type === 'PURCHASE_ORDER' ? 'ordered_quantity' : 'quantity'))
<x-layout title="{{ $title }} {{ $document->number }}">
    <div class="gp-no-print" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
        <a href="{{ route('documents.index') }}" class="gp-meta">← Tous les documents</a>
        <button type="button" onclick="window.print()" class="gp-btn gp-btn--quiet gp-btn--sm">Imprimer</button>
    </div>

    <div class="gp-receipt">
        <div style="text-align:center">
            <div class="gp-label">{{ $snapshot['context_display_name'] ?? '' }}</div>
            <h1 class="gp-display" style="margin-top:6px">{{ $title }}</h1>
            <div class="gp-meta gp-tabular">{{ $document->number }} · {{ $snapshot['reference'] ?? $snapshot['purchase_order_reference'] ?? $snapshot['session_reference'] ?? '' }}</div>
            @if($document->document_type !== 'RECEIPT' && $document->document_type !== 'CASH_CLOSURE')
                <div class="gp-meta">{{ $snapshot['supplier_display_name'] ?? '' }}</div>
            @endif
            @if($document->document_type === 'GOODS_RECEIPT')
                <div class="gp-meta">Commande {{ $snapshot['purchase_order_reference'] ?? '' }}</div>
            @endif
            @if($document->document_type === 'CASH_CLOSURE')
                <div class="gp-meta">{{ $snapshot['register_name'] ?? '' }} · {{ $snapshot['responsible_core_reference'] ?? '' }}</div>
            @endif
        </div>

        @if($document->document_type === 'CASH_CLOSURE')
            <div style="display:flex;flex-direction:column;gap:8px">
                <div class="gp-receipt__line"><span>Fonds de départ</span><span class="gp-tabular">{{ number_format($snapshot['opening_amount_xof'] ?? 0, 0, ',', ' ') }} F</span></div>
                <div class="gp-receipt__line"><span>Total entrées</span><span class="gp-tabular">{{ number_format($snapshot['total_in_xof'] ?? 0, 0, ',', ' ') }} F</span></div>
                <div class="gp-receipt__line"><span>Total sorties</span><span class="gp-tabular">{{ number_format($snapshot['total_out_xof'] ?? 0, 0, ',', ' ') }} F</span></div>
                <div class="gp-receipt__line"><span>Espèces attendues</span><span class="gp-tabular">{{ number_format($snapshot['expected_amount_xof'] ?? 0, 0, ',', ' ') }} F</span></div>
                <div class="gp-receipt__line"><span>Montant compté</span><span class="gp-tabular">{{ number_format($snapshot['counted_amount_xof'] ?? 0, 0, ',', ' ') }} F</span></div>
                @if(($snapshot['variance_xof'] ?? 0) !== 0)
                    <div class="gp-receipt__line"><span>Écart</span><span class="gp-tabular" style="color:var(--gp-error)">{{ $snapshot['variance_xof'] > 0 ? '+' : '' }}{{ number_format($snapshot['variance_xof'], 0, ',', ' ') }} F</span></div>
                    <div class="gp-hint">{{ $snapshot['variance_reason'] ?? '' }}</div>
                @endif
            </div>

            <div class="gp-receipt__line gp-receipt__line--total">
                <span>Clôturée par {{ $snapshot['closed_by_core_reference'] ?? '' }}</span>
                <span class="gp-tabular">{{ ($snapshot['variance_xof'] ?? 0) === 0 ? 'Équilibrée' : 'Écart' }}</span>
            </div>
        @else
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
        @endif

        <div class="gp-meta" style="text-align:center">Émis le {{ $document->issued_at->format('d/m/Y à H:i') }}</div>
    </div>
</x-layout>
