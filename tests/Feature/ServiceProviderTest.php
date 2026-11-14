<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\ReactsServiceProvider;
use PHPUnit\Framework\Assert;

test('the provider is registered', function () {
    $this->assertNotEmpty($this->app->getProviders(ReactsServiceProvider::class));
});

test('the configuration defaults are merged', function () {
    $config = $this->app['config'];

    $this->assertSame(Reaction::class, $config->get('pharaonic.reacts.model'));
    $this->assertSame('reactions', $config->get('pharaonic.reacts.table'));
    $this->assertNull($config->get('pharaonic.reacts.connection'));
    $this->assertSame('multiple', $config->get('pharaonic.reacts.mode'));
    $this->assertSame('default', $config->get('pharaonic.reacts.default_group'));
    $this->assertSame(['default', 'posts', 'chat'], array_keys($config->get('pharaonic.reacts.groups')));
});

test('the configuration is published to config pharaonic', function () {
    assertPublishedByEveryTag(__DIR__.'/../../config/reacts.php', $this->app->configPath('pharaonic/reacts.php'), 'config');
});

test('the migrations are published to database migrations', function () {
    assertPublishedByEveryTag(__DIR__.'/../../database/migrations', $this->app->databasePath('migrations'), 'migrations');
});

test('the package registers no routes', function () {
    // Laravel 12 serves a local disk's files through its own storage.<disk> routes.
    $routes = array_filter(Route::getRoutes()->getRoutes(), function ($route) {
        return ! str_starts_with((string) $route->getName(), 'storage.');
    });

    $this->assertCount(0, $routes);
});

/**
 * Every publish tag of the kind copies the source to the target.
 */
function assertPublishedByEveryTag(string $source, string $target, string $kind): void
{
    $source = (string) realpath($source);

    foreach (['pharaonic', 'laravel-reacts', 'pharaonic-'.$kind, 'reacts-'.$kind] as $tag) {
        $published = [];

        foreach (ServiceProvider::pathsToPublish(ReactsServiceProvider::class, $tag) as $from => $to) {
            $published[realpath($from) ?: $from] = $to;
        }

        Assert::assertArrayHasKey($source, $published, $tag);
        Assert::assertSame($target, $published[$source], $tag);
    }
}
