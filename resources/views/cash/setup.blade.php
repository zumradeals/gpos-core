{{-- Hub Caisse — aucune caisse, ou caisse fermée (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §22.1, §22.2). --}}
<x-layout title="Caisse">
    <h1 class="gp-display">Caisse</h1>

    @if(session('status'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-forest);color:var(--gp-forest);font-size:14px">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:14px">{{ session('error') }}</div>
    @endif

    @if($registers->isEmpty())
        <x-empty-state title="Créez votre première caisse" body="Donnez un nom simple à l'endroit où vous contrôlez vos espèces.">
            @if($canManageCash)
                <details style="width:100%;margin-top:10px">
                    <summary class="gp-btn gp-btn--primary" style="cursor:pointer;list-style:none;display:inline-flex">Créer une caisse</summary>
                    <form method="POST" action="{{ route('cash.registers.store') }}" class="gp-form" style="margin-top:14px;text-align:left">
                        @csrf
                        <label class="gp-field"><span>Nom</span><input type="text" name="name" required maxlength="160" placeholder="Caisse principale"></label>
                        <label class="gp-field"><span>Code (facultatif)</span><input type="text" name="code" maxlength="40"></label>
                        <button type="submit" class="gp-btn gp-btn--primary" style="align-self:flex-start">Créer</button>
                    </form>
                </details>
            @endif
        </x-empty-state>
    @else
        <div class="gp-deep">
            <div class="gp-label" style="color:var(--gp-on-deep-muted)">Votre caisse est fermée</div>
            <p class="gp-body" style="color:var(--gp-on-deep-text);margin-top:6px">Ouvrez une session avant d'encaisser en espèces.</p>
        </div>

        @foreach($registers as $register)
            <div class="gp-card">
                <strong style="font-size:15px;color:var(--gp-petrol)">{{ $register->name }}</strong>
                @if($canOperateCash)
                    <form method="POST" action="{{ route('cash.sessions.open', $register) }}" class="gp-form" style="margin-top:14px">
                        @csrf
                        <label class="gp-field"><span>Fonds de départ</span>
                            <input type="number" name="opening_amount_xof" step="1" min="0" value="0" required>
                        </label>
                        <button type="submit" class="gp-btn gp-btn--primary" style="align-self:flex-start">Ouvrir ma caisse</button>
                    </form>
                @else
                    <p class="gp-hint" style="margin-top:8px">Vous n'avez pas la permission d'ouvrir cette caisse.</p>
                @endif
            </div>
        @endforeach

        @if($canManageCash)
            <details>
                <summary class="gp-meta" style="cursor:pointer;list-style:none;color:var(--gp-copper)">+ Ajouter une autre caisse</summary>
                <form method="POST" action="{{ route('cash.registers.store') }}" class="gp-form" style="margin-top:10px">
                    @csrf
                    <label class="gp-field"><span>Nom</span><input type="text" name="name" required maxlength="160"></label>
                    <label class="gp-field"><span>Code (facultatif)</span><input type="text" name="code" maxlength="40"></label>
                    <button type="submit" class="gp-btn gp-btn--quiet gp-btn--sm" style="align-self:flex-start">Créer</button>
                </form>
            </details>
        @endif
    @endif
</x-layout>
