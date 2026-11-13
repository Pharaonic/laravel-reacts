<?php

namespace Pharaonic\Laravel\Reacts\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Pharaonic\Laravel\Reacts\Catalog\EmojiValidator;
use Pharaonic\Laravel\Reacts\ReactsServiceProvider;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Schema;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetMorphMap();
        $this->freshDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetMorphMap();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [ReactsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', self::connection());
        $app['config']->set('database.connections.secondary', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    /**
     * Free-form emoji need PCRE2 10.40 or later. PHP builds that bundle an
     * older PCRE (PHP 8.0 and 8.1 on Windows and in the official Docker
     * images) skip these tests, and run the fallback tests instead.
     */
    protected function requireEmojiProperties(): void
    {
        if (! EmojiValidator::isSupported()) {
            $this->markTestSkipped('Free-form emoji need PCRE2 10.40 or later; this PHP has PCRE '.PCRE_VERSION.'.');
        }
    }

    /**
     * The SQL statements the callback runs.
     *
     * @return list<string>
     */
    protected function queries(callable $callback): array
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $callback();
        } finally {
            $connection->disableQueryLog();
        }

        $queries = [];

        foreach ($connection->getQueryLog() as $entry) {
            $queries[] = (string) $entry['query'];
        }

        return $queries;
    }

    /**
     * The statements that mention a table.
     *
     * @param  list<string>  $queries
     * @return list<string>
     */
    protected static function touching(array $queries, string $table): array
    {
        return array_values(array_filter($queries, function (string $query) use ($table) {
            return (bool) preg_match('/["`\[]?'.preg_quote($table, '/').'["`\]]?/', $query);
        }));
    }

    /**
     * The test database: in-memory SQLite, unless REACTS_DB selects mysql or pgsql.
     *
     * @return array<string, mixed>
     */
    public static function connection(): array
    {
        $env = static function (string $name, string $default): string {
            $value = getenv($name);

            return is_string($value) && $value !== '' ? $value : $default;
        };

        switch ($env('REACTS_DB', 'sqlite')) {
            case 'mysql':
                return [
                    'driver' => 'mysql',
                    'host' => $env('REACTS_DB_HOST', '127.0.0.1'),
                    'port' => $env('REACTS_DB_PORT', '3306'),
                    'database' => $env('REACTS_DB_DATABASE', 'reacts'),
                    'username' => $env('REACTS_DB_USERNAME', 'root'),
                    'password' => $env('REACTS_DB_PASSWORD', ''),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ];
            case 'pgsql':
                return [
                    'driver' => 'pgsql',
                    'host' => $env('REACTS_DB_HOST', '127.0.0.1'),
                    'port' => $env('REACTS_DB_PORT', '5432'),
                    'database' => $env('REACTS_DB_DATABASE', 'reacts'),
                    'username' => $env('REACTS_DB_USERNAME', 'postgres'),
                    'password' => $env('REACTS_DB_PASSWORD', ''),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ];
            default:
                return [
                    'driver' => 'sqlite',
                    'database' => $env('REACTS_DB_DATABASE', ':memory:'),
                    'prefix' => '',
                ];
        }
    }

    /**
     * Drop every table, run the package migration, and create the fixture tables.
     */
    protected function freshDatabase(): void
    {
        $this->app->make(Kernel::class)->call('migrate:fresh');

        Schema::create();
    }

    /**
     * Move the tests to a database other processes and connections can open:
     * a SQLite file instead of the in-memory database. Returns the environment
     * a worker process needs to open it.
     *
     * @return array<string, string>
     */
    protected function useSharedDatabase(): array
    {
        $variables = ['REACTS_DB', 'REACTS_DB_HOST', 'REACTS_DB_PORT', 'REACTS_DB_DATABASE', 'REACTS_DB_USERNAME', 'REACTS_DB_PASSWORD'];
        $env = [];

        foreach ($variables as $name) {
            $value = getenv($name);

            if (is_string($value) && $value !== '') {
                $env[$name] = $value;
            }
        }

        if ($this->app['config']->get('database.connections.testing.driver') === 'sqlite') {
            $file = tempnam(sys_get_temp_dir(), 'laravel-reacts-');
            $this->assertIsString($file);

            $this->app['config']->set('database.connections.testing.database', $file);
            DB::purge('testing');
            $this->freshDatabase();

            $this->beforeApplicationDestroyed(function () use ($file) {
                DB::disconnect('testing');
                @unlink($file);
            });

            $env['REACTS_DB'] = 'sqlite';
            $env['REACTS_DB_DATABASE'] = $file;
        }

        return $env;
    }

    private function resetMorphMap(): void
    {
        Relation::$morphMap = [];

        if (method_exists(Relation::class, 'requireMorphMap')) {
            Relation::requireMorphMap(false);
        }
    }
}
