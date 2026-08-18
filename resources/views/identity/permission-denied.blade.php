{{-- Acteur résolu mais sans la permission commerciale requise (docs/implementation/LOT-001 §20). --}}
<x-layout title="Action non autorisée">
    <x-empty-state
        title="Vous n'avez pas cette permission ici"
        :body="'Cette action nécessite la permission « '.$permission.' » dans le contexte commercial actif. Elle peut être accordée par un responsable de ce contexte.'"
    />
</x-layout>
