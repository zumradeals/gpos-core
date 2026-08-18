<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // L'endpoint /livewire/update de Livewire n'est routé QUE par le groupe de middleware
        // 'web' par défaut — il ne passe jamais par nos middleware applicatifs propres aux routes
        // de routes/web.php (actor.required, context.active, context.required). Sans ce
        // remplacement, chaque appel Livewire ultérieur au montage (incrementLine, addLine,
        // confirmOrder, etc.) s'exécute avec un CurrentActor sans contexte actif résolu, cassant
        // tout composant Livewire qui en dépend (App\Livewire\Sell inclus).
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)->middleware([
            'web', 'actor.required', 'context.active', 'context.required',
        ]));
    }
}
