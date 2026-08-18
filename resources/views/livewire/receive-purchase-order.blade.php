{{-- « Que venez-vous de recevoir ? » (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §23). --}}
<div style="display:flex;flex-direction:column;gap:16px;max-width:640px">
    @if($errorMessage)
        <div style="padding:12px 14px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:13px;line-height:1.5">{{ $errorMessage }}</div>
    @endif

    <div class="gp-card" style="padding:0;overflow:hidden">
        @foreach($lines as $line)
            @php($remaining = \App\Domain\Commerce\Quantity::subtract((string) $line->ordered_quantity, (string) $line->received_quantity))
            <div style="padding:16px 20px;border-bottom:1px solid var(--gp-line-inner);display:flex;flex-direction:column;gap:10px" wire:key="receive-line-{{ $line->id }}">
                <strong style="font-size:14px;color:var(--gp-ink)">{{ $line->product_name_snapshot }}</strong>
                <div style="display:flex;gap:18px;flex-wrap:wrap">
                    <span class="gp-meta">Commandé <span class="gp-tabular">{{ rtrim(rtrim((string) $line->ordered_quantity, '0'), '.') ?: '0' }}</span></span>
                    <span class="gp-meta">Déjà reçu <span class="gp-tabular">{{ rtrim(rtrim((string) $line->received_quantity, '0'), '.') ?: '0' }}</span></span>
                    <span class="gp-meta">Restant <span class="gp-tabular">{{ rtrim(rtrim($remaining, '0'), '.') ?: '0' }}</span></span>
                </div>
                <label class="gp-field" style="max-width:200px">
                    <span>Reçu maintenant</span>
                    <input type="number" step="0.001" min="0" wire:model="receiveNow.{{ $line->id }}">
                </label>
            </div>
        @endforeach
    </div>

    @if(! $canReceive)
        <p class="gp-hint">Vous n'avez pas la permission de réceptionner dans ce contexte.</p>
    @else
        <button type="button" class="gp-btn gp-btn--primary gp-btn--block" wire:click="confirmReceipt" wire:loading.attr="disabled" wire:target="confirmReceipt">
            <span wire:loading.remove wire:target="confirmReceipt">Confirmer la réception</span>
            <span wire:loading wire:target="confirmReceipt">Confirmation…</span>
        </button>
    @endif
</div>
