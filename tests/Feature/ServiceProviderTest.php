<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\ReactsServiceProvider;
use Pharaonic\Laravel\Reacts\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_the_provider_is_registered(): void
    {
        $this->assertNotEmpty($this->app->getProviders(ReactsServiceProvider::class));
    }

    public function test_the_configuration_defaults_are_merged(): void
    {
        $config = $this->app['config'];

        $this->assertSame(Reaction::class, $config->get('pharaonic.reacts.model'));
        $this->assertSame('reactions', $config->get('pharaonic.reacts.table'));
        $this->assertNull($config->get('pharaonic.reacts.connection'));
        $this->assertSame('multiple', $config->get('pharaonic.reacts.mode'));
        $this->assertSame('default', $config->get('pharaonic.reacts.default_group'));
        $this->assertSame(['default', 'posts', 'chat'], array_keys($config->get('pharaonic.reacts.groups')));
    }

    public function test_the_configuration_is_published_to_config_pharaonic(): void
    {
        $this->assertPublished(__DIR__.'/../../config/reacts.php', $this->app->configPath('pharaonic/reacts.php'), 'config');
    }

    public function test_the_migrations_are_published_to_database_migrations(): void
    {
        $this->assertPublished(__DIR__.'/../../database/migrations', $this->app->databasePath('migrations'), 'migrations');
    }

    public function test_the_package_registers_no_routes(): void
    {
        $this->assertCount(0, Route::getRoutes());
    }

    private function assertPublished(string $source, string $target, string $kind): void
    {
        $source = (string) realpath($source);

        foreach (['pharaonic', 'laravel-reacts', 'pharaonic-'.$kind, 'reacts-'.$kind] as $tag) {
            $published = [];

            foreach (ServiceProvider::pathsToPublish(ReactsServiceProvider::class, $tag) as $from => $to) {
                $published[realpath($from) ?: $from] = $to;
            }

            $this->assertArrayHasKey($source, $published, $tag);
            $this->assertSame($target, $published[$source], $tag);
        }
    }
}
