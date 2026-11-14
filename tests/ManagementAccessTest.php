<?php

use Coding1688\Files\FilesServiceProvider;
use Coding1688\Files\Http\Middleware\AuthorizeManagement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\GenericUser;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ManagementAccessTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new Application;
        $app->instance('config', new Repository);
        $app->instance('files', new Filesystem);
        $app->instance('events', new Dispatcher($app));
        $app->instance('router', new Router(app('events'), $app));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance(null);
    }

    public function test_management_routes_are_disabled_by_default(): void
    {
        $provider = new FilesServiceProvider(app());
        $provider->register();
        $provider->boot();
        self::assertCount(0, app('router')->getRoutes());
    }

    public function test_enabled_routes_all_have_authorization_and_configured_prefix(): void
    {
        config(['files.management.enabled' => true, 'files.management.prefix' => 'custom/files']);
        $provider = new FilesServiceProvider(app());
        $provider->register();
        $provider->boot();
        self::assertCount(14, app('router')->getRoutes());
        foreach (app('router')->getRoutes() as $route) {
            self::assertStringStartsWith('custom/files/', $route->uri());
            self::assertContains(AuthorizeManagement::class, $route->gatherMiddleware());
            self::assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_anonymous_requests_are_rejected(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => null);
        try {
            (new AuthorizeManagement)->handle($request, fn () => self::fail('Unauthorized callback'));
        } catch (HttpException $e) {
            self::assertSame(401, $e->getStatusCode());

            return;
        }
        self::fail('Expected 401');
    }

    public function test_missing_gate_denies_authenticated_user_and_explicit_grant_allows(): void
    {
        $user = new GenericUser(['id' => 1]);
        $gate = new Gate(app(), fn () => $user);
        app()->instance(Illuminate\Contracts\Auth\Access\Gate::class, $gate);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        try {
            (new AuthorizeManagement)->handle($request, fn () => self::fail('Unauthorized callback'));
            self::fail('Expected denied gate');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $gate->define('manage-files', fn ($actor) => $actor->id === 1);
        $response = new Response('ok');
        self::assertSame($response, (new AuthorizeManagement)->handle($request, fn () => $response));
    }
}
