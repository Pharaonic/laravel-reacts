<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;
use Symfony\Component\Process\Process;

/**
 * Separate PHP processes, each with its own connection, act on one pair at
 * the same moment. On SQLite the database file serializes the writes; on
 * MySQL and PostgreSQL (REACTS_DB) they really run in parallel.
 */
class ConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    public function test_simultaneous_identical_reactions_store_one_row(): void
    {
        [$user, $post, $results] = $this->race('react', Post::class, array_fill(0, self::WORKERS, 'like'));

        $this->assertSame(1, Reaction::query()->count());
        $this->assertSame(1, count(array_filter(array_column($results, 'result'))));
        $this->assertSame(['ReactionCreated'], array_merge(...array_column($results, 'events')));
        $this->assertTrue($user->hasReacted($post, 'like'));
    }

    public function test_simultaneous_different_types_in_multiple_mode_all_coexist(): void
    {
        $types = ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'like', 'love'];
        [$user, $post] = $this->race('react', Post::class, $types);

        $this->assertSame(['angry', 'laugh', 'like', 'love', 'sad', 'wow'], $user->reactionsOn($post)->pluck('type_key')->sort()->values()->all());
    }

    public function test_simultaneous_different_types_in_single_mode_keep_one_row(): void
    {
        $types = ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'like', 'love'];
        [$user, $post, $results] = $this->race('react', SinglePost::class, $types);

        $this->assertSame(1, Reaction::query()->count());
        $this->assertSame(1, Reaction::query()->whereNotNull('single_slot_hash')->count());
        $events = array_merge(...array_column($results, 'events'));
        $this->assertSame(1, count(array_keys($events, 'ReactionCreated', true)));
        $this->assertSame([], array_keys($events, 'ReactionRemoved', true));
        $this->assertCount(1, $user->reactionsOn($post));
    }

    public function test_simultaneous_toggles_never_duplicate(): void
    {
        [$user, $post] = $this->race('toggle', Post::class, array_fill(0, self::WORKERS, 'like'));

        $this->assertLessThanOrEqual(1, Reaction::query()->count());
        $this->assertSame(Reaction::query()->count() === 1, $user->hasReacted($post, 'like'));
    }

    /**
     * @param  class-string<Post>  $postClass
     * @param  list<string>  $types  one per worker
     * @return array{0: User, 1: Post, 2: list<array{result: bool, events: list<string>}>}
     */
    private function race(string $action, string $postClass, array $types): array
    {
        $env = $this->useSharedDatabase();
        $user = User::create();
        $post = $postClass::create();

        // Leave the workers time to boot, then let them all start together.
        $start = (string) (microtime(true) + 3);
        $processes = [];

        foreach ($types as $type) {
            $process = new Process(
                [PHP_BINARY, __DIR__.'/../Concurrency/worker.php', $action, $postClass, (string) $user->getKey(), (string) $post->getKey(), $type, $start],
                null, $env, null, 60
            );
            $process->start();
            $processes[] = $process;
        }

        $results = [];

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $result = json_decode($process->getOutput(), true);
            $this->assertIsArray($result, $process->getOutput());
            $results[] = $result;
        }

        /** @var list<array{result: bool, events: list<string>}> $results */
        return [$user, $post, $results];
    }
}
