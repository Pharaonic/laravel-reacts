<?php

use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use PHPUnit\Framework\Assert;
use Symfony\Component\Process\Process;

/*
 * Separate PHP processes, each with its own connection, act on one pair at
 * the same moment. On SQLite the database file serializes the writes; on
 * MySQL and PostgreSQL (REACTS_DB) they really run in parallel.
 */

const REACTS_WORKERS = 8;

test('simultaneous identical reactions store one row', function () {
    [$user, $post, $results] = reactionRace($this->useSharedDatabase(), 'react', Post::class, array_fill(0, REACTS_WORKERS, 'like'));

    $this->assertSame(1, Reaction::query()->count());
    $this->assertSame(1, count(array_filter(array_column($results, 'result'))));
    $this->assertSame(['ReactionCreated'], array_merge(...array_column($results, 'events')));
    $this->assertTrue($user->hasReacted($post, 'like'));
});

test('simultaneous different types in multiple mode all coexist', function () {
    $types = ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'like', 'love'];
    [$user, $post] = reactionRace($this->useSharedDatabase(), 'react', Post::class, $types);

    $this->assertSame(['angry', 'laugh', 'like', 'love', 'sad', 'wow'], $user->reactionsOn($post)->pluck('type_key')->sort()->values()->all());
});

test('simultaneous different types in single mode keep one row', function () {
    $types = ['like', 'love', 'laugh', 'wow', 'sad', 'angry', 'like', 'love'];
    [$user, $post, $results] = reactionRace($this->useSharedDatabase(), 'react', SinglePost::class, $types);

    $this->assertSame(1, Reaction::query()->count());
    $this->assertSame(1, Reaction::query()->whereNotNull('single_slot_hash')->count());
    $events = array_merge(...array_column($results, 'events'));
    $this->assertSame(1, count(array_keys($events, 'ReactionCreated', true)));
    $this->assertSame([], array_keys($events, 'ReactionRemoved', true));
    $this->assertCount(1, $user->reactionsOn($post));
});

test('simultaneous toggles never duplicate', function () {
    [$user, $post] = reactionRace($this->useSharedDatabase(), 'toggle', Post::class, array_fill(0, REACTS_WORKERS, 'like'));

    $this->assertLessThanOrEqual(1, Reaction::query()->count());
    $this->assertSame(Reaction::query()->count() === 1, $user->hasReacted($post, 'like'));
});

/**
 * Start one worker per type on one pair at the same moment and collect what each returned.
 *
 * @param  array<string, string>  $env
 * @param  class-string<Post>  $postClass
 * @param  list<string>  $types  one per worker
 * @return array{0: User, 1: Post, 2: list<array{result: bool, events: list<string>}>}
 */
function reactionRace(array $env, string $action, string $postClass, array $types): array
{
    $user = User::create();
    $post = $postClass::create();

    // Leave the workers time to boot, then let them all start together.
    $start = (string) (microtime(true) + 3);
    $processes = [];

    foreach ($types as $type) {
        $process = new Process(
            [PHP_BINARY, __DIR__.'/../Processes/worker.php', $action, $postClass, (string) $user->getKey(), (string) $post->getKey(), $type, $start],
            null, $env, null, 60
        );
        $process->start();
        $processes[] = $process;
    }

    $results = [];

    foreach ($processes as $process) {
        $process->wait();
        Assert::assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $result = json_decode($process->getOutput(), true);
        Assert::assertIsArray($result, $process->getOutput());
        $results[] = $result;
    }

    /** @var list<array{result: bool, events: list<string>}> $results */
    return [$user, $post, $results];
}
