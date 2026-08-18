{{-- Documents commerciaux (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §13). --}}
<x-layout title="Documents">
    <h1 class="gp-display">Documents</h1>

    @if($documents->isEmpty())
        <x-empty-state title="Aucun document pour le moment" body="Chaque vente confirmée produit un reçu qui apparaîtra ici." />
    @else
        <div class="gp-card" style="padding:0;overflow:hidden">
            @foreach($documents as $document)
                <a href="{{ route('documents.show', $document) }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;color:inherit;border-bottom:1px solid var(--gp-line-inner)">
                    <span class="gp-meta gp-tabular">{{ $document->number }}</span>
                    <span class="gp-money gp-tabular">{{ number_format($document->snapshot['total_xof'] ?? 0, 0, ',', ' ') }} F</span>
                    <span class="gp-meta">{{ $document->issued_at->diffForHumans() }}</span>
                </a>
            @endforeach
        </div>
    @endif
</x-layout>
