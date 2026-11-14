<?php

use Coding1688\Files\FilesServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_defaults_register_without_host_configuration(): void
    {
        $app = new Application;
        $app->instance('config', new Repository);
        (new FilesServiceProvider($app))->register();
        self::assertIsArray(config('files'));
        self::assertNotEmpty(config('files'));
    }

    public function test_canonical_config_wins_and_empty_lists_replace_defaults(): void
    {
        $app = new Application;
        $app->instance('config', new Repository([
            'pl' => ['files' => ['test' => ['legacy' => true, 'value' => 'old'], 'locales' => ['ja']]],
            'files' => ['test' => ['value' => 'new'], 'locales' => []],
        ]));
        (new FilesServiceProvider($app))->register();
        self::assertSame('new', config('files.test.value'));
        self::assertTrue(config('files.test.legacy'));
        self::assertSame([], config('files.locales'));
    }

    public function test_legacy_configuration_is_accepted(): void
    {
        $app = new Application;
        $app->instance('config', new Repository(['pl' => ['files' => ['locales' => ['ko']]]]));
        (new FilesServiceProvider($app))->register();
        self::assertSame(['ko'], config('files.locales'));
    }

    public function test_config_can_be_published(): void
    {
        $app = new Application;
        $app->instance('config', new Repository);
        $provider = new FilesServiceProvider($app);
        $provider->register();
        $provider->boot();
        self::assertNotEmpty(ServiceProvider::pathsToPublish(FilesServiceProvider::class, 'files-config'));
    }
}
