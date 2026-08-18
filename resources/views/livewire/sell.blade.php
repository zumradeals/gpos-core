{{-- Parcours de vente — benchmark UX (docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §18). --}}
<div class="gp-sell-layout">
    <div style="display:flex;flex-direction:column;gap:14px">
        <div class="gp-search">
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Rechercher un produit, un code…" autofocus>
        </div>

        @if($products->isEmpty())
            <x-empty-state title="Aucun produit ne correspond" body="Essayez un autre mot, ou ajoutez ce produit au catalogue.">
                <a href="{{ route('products.index') }}" class="gp-btn gp-btn--quiet gp-btn--sm">Aller au catalogue →</a>
            </x-empty-state>
        @else
            <div class="gp-product-grid">
                @foreach($products as $product)
                    @php($outOfStock = $product->track_stock && (float) optional($product->stockBalance)->quantity <= 0)
                    <button type="button" class="gp-product-tile" wire:click="addProduct('{{ $product->id }}')" @disabled($outOfStock) title="{{ $outOfStock ? 'Rupture de stock' : '' }}">
                        <strong>{{ $product->name }}</strong>
                        <span class="gp-tabular">{{ number_format($product->sale_price_xof, 0, ',', ' ') }} F</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="gp-cart">
        <div class="gp-label">Panier</div>

        @if($errorMessage)
            <div style="padding:12px 14px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:13px;line-height:1.5">{{ $errorMessage }}</div>
        @endif

        @if($lines->isEmpty())
            <p class="gp-hint">Ajoutez un article pour commencer une vente.</p>
        @else
            <div>
                @foreach($lines as $line)
                    <div class="gp-cart-line" wire:key="line-{{ $line->id }}">
                        <div class="gp-cart-line__info">
                            <div class="gp-cart-line__name">{{ $line->product_name_snapshot }}</div>
                            <div class="gp-cart-line__price">{{ number_format($line->unit_price_xof, 0, ',', ' ') }} F / {{ $line->unit_label_snapshot }}</div>
                        </div>
                        <div class="gp-qty-stepper">
                            <button type="button" wire:click="decrementLine('{{ $line->id }}')" aria-label="Diminuer">−</button>
                            <span class="gp-tabular">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') ?: '0' }}</span>
                            <button type="button" wire:click="incrementLine('{{ $line->id }}')" aria-label="Augmenter">+</button>
                        </div>
                        <span class="gp-money gp-tabular">{{ number_format($line->line_total_xof, 0, ',', ' ') }} F</span>
                        <button type="button" wire:click="removeLine('{{ $line->id }}')" aria-label="Retirer" style="border:0;background:transparent;color:var(--gp-faint);cursor:pointer;font-size:18px;line-height:1">×</button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="gp-cart-total">
            <span class="gp-meta">Total</span>
            <strong class="gp-tabular">{{ number_format($sale->total_xof, 0, ',', ' ') }} F</strong>
        </div>

        @if(! $canSell)
            <p class="gp-hint">Vous n'avez pas la permission d'encaisser dans ce contexte.</p>
        @else
            <button type="button" class="gp-btn gp-btn--primary gp-btn--block" wire:click="confirmCash" wire:loading.attr="disabled" wire:target="confirmCash" @disabled($lines->isEmpty())>
                <span wire:loading.remove wire:target="confirmCash">Encaisser · {{ number_format($sale->total_xof, 0, ',', ' ') }} F comptant</span>
                <span wire:loading wire:target="confirmCash">Confirmation…</span>
            </button>
        @endif
    </div>
</div>
