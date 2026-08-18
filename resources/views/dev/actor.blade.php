{{-- Outil de développement — jamais accessible en production (routes/web.php). --}}
<x-layout title="Devenir un acteur de développement">
    <div class="gp-stack" style="max-width:480px">
        <h1 class="gp-display">Devenir un acteur (développement)</h1>
        <p class="gp-hint">Sert uniquement à QA manuellement les écrans selon différentes identités/permissions. Sans effet en production.</p>
        <form method="POST" action="{{ route('dev.actor.become') }}" class="gp-form">
            @csrf
            <label class="gp-field">
                <span>Référence Core</span>
                <input type="text" name="core_identity_reference" value="{{ old('core_identity_reference') }}" placeholder="IDN-PER-..." required>
            </label>
            <label class="gp-field">
                <span>Étiquette (facultative)</span>
                <input type="text" name="core_identity_label" value="{{ old('core_identity_label') }}" placeholder="Vendeuse boutique centre">
            </label>
            <button type="submit" class="gp-btn gp-btn--primary">Devenir cet acteur</button>
        </form>
    </div>
</x-layout>
