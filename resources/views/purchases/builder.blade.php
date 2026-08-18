{{-- Construction d'une commande d'achat DRAFT (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §22). --}}
<x-layout title="Nouvel achat">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
        <div>
            <a href="{{ route('purchases.hub') }}" class="gp-meta">← Acheter</a>
            <h1 class="gp-display" style="margin-top:6px">Nouvel achat</h1>
        </div>
    </div>

    <livewire:purchase-order-builder :purchase-order-id="$purchaseOrderId" :initial-product-id="$initialProductId" />
</x-layout>
