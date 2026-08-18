{{-- Construction d'une commande d'achat DRAFT (docs/implementation/LOT-002-PURCHASING-SUPPLY.md §22). --}}
<div class="gp-sell-layout">
    <div style="display:flex;flex-direction:column;gap:14px">
        <div class="gp-search">
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Rechercher un produit, un code…">
        </div>

        @if($products->isEmpty())
            <x-empty-state title="Aucun produit ne correspond" body="Essayez un autre mot, ou ajoutez ce produit au catalogue.">
                <a href="{{ route('products.index') }}" class="gp-btn gp-btn--quiet gp-btn--sm">Aller au catalogue →</a>
            </x-empty-state>
        @else
            <div class="gp-product-grid">
                @foreach($products as $product)
                    <button type="button" class="gp-product-tile" wire:click="selectProduct('{{ $product->id }}')" @if($selectedProductId === (string) $product->id) style="border-color:var(--gp-copper)" @endif>
                        <strong>{{ $product->name }}</strong>
                        <span class="gp-hint">{{ $product->unit_label }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="gp-cart">
        <div class="gp-label">Nouvelle ligne</div>

        @if($errorMessage)
            <div style="padding:12px 14px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:13px;line-height:1.5">{{ $errorMessage }}</div>
        @endif

        <form wire:submit.prevent="addLine" class="gp-form">
            <label class="gp-field">
                <span>Produit sélectionné</span>
                <input type="text" readonly value="{{ optional($products->firstWhere('id', $selectedProductId))->name ?? 'Choisissez un produit ci-contre' }}">
            </label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <label class="gp-field"><span>Quantité</span><input type="number" step="0.001" min="0.001" wire:model="quantity"></label>
                <label class="gp-field"><span>Coût unitaire (XOF)</span><input type="number" step="1" min="0" wire:model="unitCostXof"></label>
            </div>
            <button type="submit" class="gp-btn gp-btn--quiet gp-btn--sm" @disabled($selectedProductId === '')>Ajouter la ligne</button>
        </form>

        <div class="gp-label" style="margin-top:6px">Commande</div>

        @if($lines->isEmpty())
            <p class="gp-hint">Ajoutez un produit pour commencer cette commande.</p>
        @else
            <div>
                @foreach($lines as $line)
                    <div class="gp-cart-line" wire:key="line-{{ $line->id }}">
                        <div class="gp-cart-line__info">
                            <div class="gp-cart-line__name">{{ $line->product_name_snapshot }}</div>
                            <div class="gp-cart-line__price">{{ rtrim(rtrim((string) $line->ordered_quantity, '0'), '.') ?: '0' }} {{ $line->unit_label_snapshot }} × {{ number_format($line->unit_cost_xof, 0, ',', ' ') }} F</div>
                        </div>
                        <span class="gp-money gp-tabular">{{ number_format($line->line_total_xof, 0, ',', ' ') }} F</span>
                        <button type="button" wire:click="removeLine('{{ $line->id }}')" aria-label="Retirer" style="border:0;background:transparent;color:var(--gp-faint);cursor:pointer;font-size:18px;line-height:1">×</button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="gp-cart-total">
            <span class="gp-meta">Total</span>
            <strong class="gp-tabular">{{ number_format($order->total_xof, 0, ',', ' ') }} F</strong>
        </div>

        @if(! $canManagePurchases)
            <p class="gp-hint">Vous n'avez pas la permission de confirmer une commande d'achat dans ce contexte.</p>
        @else
            <button type="button" class="gp-btn gp-btn--primary gp-btn--block" wire:click="confirmOrder" wire:loading.attr="disabled" wire:target="confirmOrder" @disabled($lines->isEmpty())>
                <span wire:loading.remove wire:target="confirmOrder">Confirmer la commande · {{ number_format($order->total_xof, 0, ',', ' ') }} F</span>
                <span wire:loading wire:target="confirmOrder">Confirmation…</span>
            </button>
        @endif
    </div>
</div>
