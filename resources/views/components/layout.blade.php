@props(['title' => 'G-POS', 'shell' => true])
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — G-POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="gp">
@if($shell && ($currentActor ?? null)?->hasActiveContext())
    <x-app-shell>
        {{ $slot }}
    </x-app-shell>
@else
    <div class="gp-page" style="max-width:560px;margin:0 auto;padding-top:64px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <span class="gp-topbar__mark">G</span>
            <strong style="font-size:15px;color:var(--gp-petrol)">G-POS</strong>
        </div>
        {{ $slot }}
    </div>
@endif
@livewireScripts
</body>
</html>
