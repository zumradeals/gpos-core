<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\CoreSessionGateway;
use App\Infrastructure\Identity\DevCoreSessionGateway;
use App\Infrastructure\Identity\NullCoreSessionGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Lie CoreSessionGateway. DevCoreSessionGateway n'est jamais liée en production, quelle que soit
 * la configuration — voir docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.3.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CoreSessionGateway::class, function (): CoreSessionGateway {
            $devIdentityAllowed = ! $this->app->environment('production')
                && (bool) config('gpos.dev_identity.enabled', false);

            return $devIdentityAllowed
                ? $this->app->make(DevCoreSessionGateway::class)
                : $this->app->make(NullCoreSessionGateway::class);
        });
    }
}
