{{--
    Reçu — lit uniquement le snapshot figé au moment de l'émission, jamais l'état courant du
    produit (docs/architecture/SATELLITE-CONTRACT.md §13).
--}}
@php($snapshot = $document->snapshot)
<x-layout title="Reçu {{ $document->number }}">
    <div class="gp-no-print" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
        <a href="{{ route('documents.index') }}" class="gp-meta">← Tous les documents</a>
        <button type="button" onclick="window.print()" class="gp-btn gp-btn--quiet gp-btn--sm">Imprimer</button>
    </div>

    <div class="gp-receipt">
        <div style="text-align:center">
            <div class="gp-label">{{ $snapshot['context_display_name'] ?? '' }}</div>
            <h1 class="gp-display" style="margin-top:6px">Reçu</h1>
            <div class="gp-meta gp-tabular">{{ $document->number }} · {{ $snapshot['reference'] ?? '' }}</div>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px">
            @foreach($snapshot['lines'] ?? [] as $line)
                <div class="gp-receipt__line">
                    <span>{{ $line['product_name'] }} × {{ rtrim(rtrim((string) $line['quantity'], '0'), '.') ?: '0' }}</span>
                    <span class="gp-tabular">{{ number_format($line['line_total_xof'], 0, ',', ' ') }} F</span>
                </div>
            @endforeach
        </div>

        <div class="gp-receipt__line gp-receipt__line--total">
            <span>Total payé — {{ $snapshot['payment_method'] ?? 'CASH' }}</span>
            <span class="gp-tabular">{{ number_format($snapshot['total_xof'] ?? 0, 0, ',', ' ') }} F</span>
        </div>

        <div class="gp-meta" style="text-align:center">Émis le {{ $document->issued_at->format('d/m/Y à H:i') }}</div>
    </div>
</x-layout>
