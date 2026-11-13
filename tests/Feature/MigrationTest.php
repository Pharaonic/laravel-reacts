<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;
use RuntimeException;

class MigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_13_000001_create_pharaonic_reactions_table';

    public function test_migrate_creates_the_reactions_table(): void
    {
        $this->assertTrue(Schema::hasColumns('reactions', [
            'id', 'reactor_type', 'reactor_id', 'reactable_type', 'reactable_id', 'type_key',
            'identity_hash', 'pair_hash', 'single_slot_hash', 'created_at', 'updated_at',
        ]));
        $this->assertSame(1, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    public function test_identity_hash_is_unique(): void
    {
        DB::table('reactions')->insert($this->row('a'));

        $this->expectException(QueryException::class);
        DB::table('reactions')->insert($this->row('a'));
    }

    public function test_single_slot_hash_is_unique_but_nullable(): void
    {
        DB::table('reactions')->insert($this->row('a', null));
        DB::table('reactions')->insert($this->row('b', null));
        DB::table('reactions')->insert($this->row('c', 'slot'));

        $this->expectException(QueryException::class);
        DB::table('reactions')->insert($this->row('d', 'slot'));
    }

    public function test_rollback_drops_the_table_and_migrate_creates_it_again(): void
    {
        $kernel = $this->app->make(Kernel::class);

        $this->assertSame(0, $kernel->call('migrate:rollback'));
        $this->assertFalse(Schema::hasTable('reactions'));

        $this->assertSame(0, $kernel->call('migrate'));
        $this->assertTrue(User::create()->react(Post::create(), 'like'));
    }

    public function test_the_migration_refuses_to_run_over_an_existing_table(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->call('migrate:rollback');
        Schema::create('reactions', function (Blueprint $table) {
            $table->bigIncrements('id');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The [reactions] table already exists');
        $kernel->call('migrate');
    }

    /**
     * @return array<string, string|null>
     */
    private function row(string $identity, ?string $slot = null): array
    {
        return [
            'reactor_type' => 'user', 'reactor_id' => '1', 'reactable_type' => 'post', 'reactable_id' => '1', 'type_key' => 'like',
            'identity_hash' => str_pad($identity, 64, '0'), 'pair_hash' => str_repeat('p', 64),
            'single_slot_hash' => $slot === null ? null : str_pad($slot, 64, '0'),
        ];
    }
}
