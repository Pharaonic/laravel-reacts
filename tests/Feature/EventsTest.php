<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Reacts\Events\ReactionCreated;
use Pharaonic\Laravel\Reacts\Events\ReactionRemoved;
use Pharaonic\Laravel\Reacts\Events\ReactionReplaced;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;
use RuntimeException;

class EventsTest extends TestCase
{
    private const EVENTS = [ReactionCreated::class, ReactionReplaced::class, ReactionRemoved::class];

    public function test_created_once_per_insert_and_never_for_a_duplicate(): void
    {
        Event::fake(self::EVENTS);
        $user = User::create();
        $post = Post::create();

        $user->react($post, 'like');
        $user->react($post, 'like');
        $user->react($post, 'love');

        Event::assertDispatchedTimes(ReactionCreated::class, 2);
        Event::assertDispatched(ReactionCreated::class, function (ReactionCreated $event) use ($user, $post) {
            return $event->reaction->type_key === 'like' && $event->reactor === $user && $event->reactable === $post && $event->reaction->exists;
        });
        Event::assertNotDispatched(ReactionReplaced::class);
        Event::assertNotDispatched(ReactionRemoved::class);
    }

    public function test_removed_only_when_a_row_was_deleted(): void
    {
        $user = User::create();
        $post = Post::create();
        Event::fake(self::EVENTS);

        $user->unreact($post, 'like');
        $user->clearReactions($post);
        Event::assertNotDispatched(ReactionRemoved::class);

        $user->react($post, 'like');
        $user->react($post, 'love');
        $user->unreact($post, 'like');
        $user->toggleReaction($post, 'love');
        $user->react($post, 'wow');
        $user->react($post, 'sad');
        $this->assertSame(2, $user->clearReactions($post));

        Event::assertDispatchedTimes(ReactionRemoved::class, 4);
        Event::assertDispatched(ReactionRemoved::class, function (ReactionRemoved $event) {
            return ! $event->reaction->exists && $event->reaction->type_key === 'like';
        });
    }

    public function test_single_mode_replacement_is_one_replaced_event(): void
    {
        $user = User::create();
        $post = SinglePost::create();
        Event::fake(self::EVENTS);

        $user->react($post, 'like');
        $user->react($post, 'love');
        $user->react($post, 'love');
        $user->toggleReaction($post, 'wow');

        Event::assertDispatchedTimes(ReactionCreated::class, 1);
        Event::assertDispatchedTimes(ReactionReplaced::class, 2);
        Event::assertNotDispatched(ReactionRemoved::class);
        Event::assertDispatched(ReactionReplaced::class, function (ReactionReplaced $event) use ($user, $post) {
            return $event->current->type_key === 'love' && count($event->previous) === 1
                && $event->previous[0]->type_key === 'like' && ! $event->previous[0]->exists
                && $event->reactor === $user && $event->reactable === $post;
        });
    }

    public function test_events_follow_the_write_own_transaction(): void
    {
        $user = User::create();
        $post = Post::create();
        $levels = [];
        Event::listen(ReactionCreated::class, function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });

        DB::transaction(function () use ($user, $post) {
            $user->react($post, 'like');
        });
        $user->react($post, 'love');

        $this->assertSame([1, 0], $levels);
    }

    public function test_a_failed_write_dispatches_nothing(): void
    {
        config(['pharaonic.reacts.policy' => \Pharaonic\Laravel\Reacts\Tests\Fixtures\NoSelfReactionPolicy::class]);
        $post = SinglePost::create();
        $user = User::create();
        $user->react($post, 'like');
        Event::fake(self::EVENTS);

        try {
            $user->react($post, 'angry');
        } catch (\Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed $exception) {
            $this->assertSame(['like'], $user->reactionsOn($post)->pluck('type_key')->all());
        }

        Event::assertNotDispatched(ReactionReplaced::class);
        Event::assertNotDispatched(ReactionCreated::class);
    }

    public function test_cleanup_on_delete_dispatches_no_removed_event(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->react($post, 'like');
        Event::fake(self::EVENTS);

        $post->delete();

        $this->assertSame(0, Reaction::query()->count());
        Event::assertNotDispatched(ReactionRemoved::class);
    }

    public function test_a_cancelled_insert_throws_and_dispatches_nothing(): void
    {
        Reaction::creating(function () {
            return false;
        });
        Event::fake(self::EVENTS);

        try {
            User::create()->react(Post::create(), 'like');
            $this->fail('A cancelled insert reported a change.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cancelled saving the reaction', $exception->getMessage());
        }

        $this->assertSame(0, Reaction::query()->count());
        Event::assertNotDispatched(ReactionCreated::class);
    }
}
