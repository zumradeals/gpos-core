{{-- Contexte commercial actif mais suspendu — toute mutation reste bloquée (docs/implementation/LOT-001 §20). --}}
<x-layout title="Contexte suspendu">
    <x-empty-state
        title="{{ $context->display_name }} est suspendue"
        body="Ce contexte commercial est temporairement suspendu. Consultez un responsable pour en connaître la raison ; aucune opération n'est possible tant que la suspension est active."
    />
</x-layout>
