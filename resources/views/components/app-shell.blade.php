{{--
    Coquille applicative — navigation progressive LOT-001 (docs/implementation/LOT-001-APP-SHELL-
    COMMERCE-SLICE.md §16) : Accueil, Vendre, Produits, Stock, Documents. « Vendre » est l'action
    centrale évidente en mobile (barre inférieure), pas noyée dans un menu.
--}}
@php
    $context = $currentActor?->activeContext();
    $navItems = collect([
        ['route' => 'home', 'label' => 'Accueil', 'visible' => true],
        ['route' => 'sell.show', 'label' => 'Vendre', 'visible' => $currentActor?->can(\App\Domain\Commerce\CommercialPermission::SELL)],
        ['route' => 'products.index', 'label' => 'Produits', 'visible' => true],
        ['route' => 'stock.index', 'label' => 'Stock', 'visible' => $currentActor?->can(\App\Domain\Commerce\CommercialPermission::VIEW_STOCK)],
        ['route' => 'documents.index', 'label' => 'Documents', 'visible' => $currentActor?->can(\App\Domain\Commerce\CommercialPermission::VIEW_DOCUMENTS)],
    ])->where('visible', true);
@endphp
<div style="min-height:100vh;display:flex;flex-direction:column">
    <header class="gp-topbar">
        <a href="{{ route('home') }}" style="display:flex;align-items:center;gap:10px;color:inherit">
            <span class="gp-topbar__mark">G</span>
            <strong style="font-size:15px">G-POS</strong>
        </a>
        <nav aria-label="Navigation principale">
            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'].'*')) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>
        @if($context)
            <details class="gp-tools-menu" style="margin-left:auto;position:relative">
                <summary class="gp-topbar__context" style="list-style:none;cursor:pointer">
                    <span>{{ $context->display_name }}</span>
                </summary>
                <div style="position:absolute;top:3rem;right:0;z-index:30;width:16rem;padding:10px;border-radius:14px;background:var(--gp-card);border:1px solid var(--gp-line);box-shadow:0 1rem 2.5rem rgba(20,49,77,.18);display:flex;flex-direction:column;gap:6px">
                    @foreach(($myCommercialContextMemberships ?? collect()) as $membership)
                        <form method="POST" action="{{ route('contexts.select') }}">
                            @csrf
                            <input type="hidden" name="context_id" value="{{ $membership->context_id }}">
                            <button type="submit" style="width:100%;text-align:left;border:0;background:transparent;padding:9px 10px;border-radius:9px;font:inherit;font-size:13px;color:var(--gp-ink);cursor:pointer;font-weight:{{ $membership->context_id === $context->id ? '700' : '400' }}">
                                {{ $membership->context->display_name }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </details>
        @endif
    </header>

    <main class="gp-page" style="flex:1;width:100%">
        {{ $slot }}
    </main>

    <nav class="gp-tabbar" aria-label="Navigation mobile">
        @foreach($navItems as $item)
            @if($item['route'] === 'sell.show')
                <a href="{{ route($item['route']) }}" class="gp-tabbar__sell" @if(request()->routeIs('sell.*')) aria-current="page" @endif>
                    <span class="gp-tabbar__icon">＋</span>
                </a>
            @else
                <a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'].'*')) aria-current="page" @endif>
                    <span class="gp-tabbar__icon"></span>
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>
</div>
