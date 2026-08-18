<x-layout title="Réceptionner">
    <a href="{{ route('purchases.hub') }}" class="gp-meta">← Acheter</a>
    <h1 class="gp-display" style="margin-top:6px">Que venez-vous de recevoir ?</h1>

    <livewire:receive-purchase-order :purchase-order-id="$purchaseOrderId" />
</x-layout>
