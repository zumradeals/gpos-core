{{-- Sélection du contexte commercial actif — aucun contexte deviné au-delà du cas trivial. --}}
<x-layout title="Choisir un contexte">
    <div class="gp-stack">
        <h1 class="gp-display">Quelle activité voulez-vous ouvrir ?</h1>

        @if(($myCommercialContextMemberships ?? collect())->isEmpty())
            <x-empty-state
                title="Aucun contexte commercial ne vous est encore ouvert"
                body="Un responsable doit vous rattacher à une activité avant que vous puissiez utiliser G-POS."
            />
        @else
            <div class="gp-context-list">
                @foreach($myCommercialContextMemberships as $membership)
                    <form method="POST" action="{{ route('contexts.select') }}">
                        @csrf
                        <input type="hidden" name="context_id" value="{{ $membership->context_id }}">
                        <button type="submit" class="gp-action-card" @disabled($membership->context->status !== \App\Models\CommercialContext::STATUS_ACTIVE)>
                            <strong>{{ $membership->context->display_name }}</strong>
                            <span class="gp-status-pill gp-status-pill--{{ strtolower($membership->context->status) }}">{{ $membership->context->status === \App\Models\CommercialContext::STATUS_ACTIVE ? 'Active' : 'Suspendue' }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
</x-layout>
