{{-- Clôturer ma caisse (docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §24). --}}
<x-layout title="Clôturer ma caisse">
    <a href="{{ route('cash.hub') }}" class="gp-meta">← Ma caisse</a>
    <h1 class="gp-display" style="margin-top:6px">Clôturer ma caisse</h1>

    <div class="gp-card">
        <span class="gp-meta">Espèces attendues</span>
        <div class="gp-tabular" style="font-family:var(--gp-font-display);font-size:28px;color:var(--gp-petrol)">{{ number_format($expected, 0, ',', ' ') }} F</div>
    </div>

    @if(session('error'))
        <div style="padding:12px 16px;border-radius:12px;background:var(--gp-tint-error);color:var(--gp-error);font-size:14px">
            @if(session('variance_xof') !== null)
                <strong>Écart : {{ session('variance_xof') > 0 ? '+' : '' }}{{ number_format(session('variance_xof'), 0, ',', ' ') }} F</strong><br>
            @endif
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('cash.closure.store') }}" class="gp-form">
        @csrf
        <label class="gp-field">
            <span>Combien avez-vous réellement en caisse ?</span>
            <input type="number" name="counted_amount_xof" step="1" min="0" required value="{{ old('counted_amount_xof') }}" autofocus>
        </label>
        <label class="gp-field">
            <span>Expliquez un écart éventuel (facultatif si le compte est exact)</span>
            <textarea name="variance_reason" rows="3" maxlength="1000">{{ old('variance_reason') }}</textarea>
        </label>
        <button type="submit" class="gp-btn gp-btn--primary" style="align-self:flex-start">Confirmer la clôture</button>
    </form>
</x-layout>
