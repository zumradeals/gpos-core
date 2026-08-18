<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\CoreSessionGateway;
use App\Infrastructure\Identity\DevCoreSessionGateway;
use App\Infrastructure\Identity\NullCoreSessionGateway;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use RuntimeException;
use Tests\TestCase;

/**
 * docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.3 : l'acteur de développement doit
 * être IMPOSSIBLE à activer silencieusement en production — double verrou (service provider +
 * garde interne de la classe elle-même).
 */
final class DevIdentityProductionTest extends TestCase
{
    public function test_dev_identity_class_refuses_to_construct_in_production_even_if_configured(): void
    {
        config(['gpos.dev_identity.enabled' => true, 'gpos.dev_identity.core_identity_reference' => 'IDN-SHOULD-NEVER-RESOLVE']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        new DevCoreSessionGateway($this->app);
    }

    public function test_dev_identity_class_refuses_to_resolve_in_production_even_if_already_constructed(): void
    {
        // Construite hors production puis appelée après un changement d'environnement : le
        // second verrou (dans resolve(), pas seulement le constructeur) doit aussi tenir.
        $gateway = new DevCoreSessionGateway($this->app);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $gateway->resolve(Request::create('/'));
    }

    public function test_the_bound_gateway_is_never_the_dev_gateway_in_production(): void
    {
        config(['gpos.dev_identity.enabled' => true, 'gpos.dev_identity.core_identity_reference' => 'IDN-SHOULD-NEVER-RESOLVE']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->app->forgetInstance(CoreSessionGateway::class);

        $gateway = $this->app->make(CoreSessionGateway::class);

        self::assertInstanceOf(NullCoreSessionGateway::class, $gateway);
        self::assertNull($gateway->resolve(Request::create('/')));
    }

    public function test_the_bound_gateway_is_the_dev_gateway_outside_production_when_enabled(): void
    {
        config(['gpos.dev_identity.enabled' => true, 'gpos.dev_identity.core_identity_reference' => 'IDN-DEV-OK']);
        $this->app->forgetInstance(CoreSessionGateway::class);

        $gateway = $this->app->make(CoreSessionGateway::class);

        self::assertInstanceOf(DevCoreSessionGateway::class, $gateway);
        self::assertSame('IDN-DEV-OK', $gateway->resolve($this->requestWithSession())->reference);
    }

    private function requestWithSession(): Request
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();

        $request = Request::create('/');
        $request->setLaravelSession($session);

        return $request;
    }
}
