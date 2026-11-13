<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

test('migrate creates the reactions table', function () {
    $this->assertTrue(Schema::hasColumns('reactions', [
        'id', 'reactor_type', 'reactor_id', 'reactable_type', 'reactable_id', 'type_key',
        'identity_hash', 'pair_hash', 'single_slot_hash', 'created_at', 'updated_at',
    ]));
    $this->assertSame(1, DB::table('migrations')->where('migration', REACTS_MIGRATION)->count());
});

test('identity hash is unique', function () {
    DB::table('reactions')->insert(migrationRow('a'));

    $this->expectException(QueryException::class);
    DB::table('reactions')->insert(migrationRow('a'));
});

test('single slot hash is unique but nullable', function () {
    DB::table('reactions')->insert(migrationRow('a', null));
    DB::table('reactions')->insert(migrationRow('b', null));
    DB::table('reactions')->insert(migrationRow('c', 'slot'));

    $this->expectException(QueryException::class);
    DB::table('reactions')->insert(migrationRow('d', 'slot'));
});

test('rollback drops the table and migrate creates it again', function () {
    $kernel = $this->app->make(Kernel::class);

    $this->assertSame(0, $kernel->call('migrate:rollback'));
    $this->assertFalse(Schema::hasTable('reactions'));

    $this->assertSame(0, $kernel->call('migrate'));
    $this->assertTrue(User::create()->react(Post::create(), 'like'));
});

test('the migration refuses to run over an existing table', function () {
    $kernel = $this->app->make(Kernel::class);
    $kernel->call('migrate:rollback');
    Schema::create('reactions', function (Blueprint $table) {
        $table->bigIncrements('id');
    });

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('The [reactions] table already exists');
    $kernel->call('migrate');
});

/**
 * A raw row with the given identity and slot hashes.
 *
 * @return array<string, string|null>
 */
function migrationRow(string $identity, ?string $slot = null): array
{
    return [
        'reactor_type' => 'user', 'reactor_id' => '1', 'reactable_type' => 'post', 'reactable_id' => '1', 'type_key' => 'like',
        'identity_hash' => str_pad($identity, 64, '0'), 'pair_hash' => str_repeat('p', 64),
        'single_slot_hash' => $slot === null ? null : str_pad($slot, 64, '0'),
    ];
}
