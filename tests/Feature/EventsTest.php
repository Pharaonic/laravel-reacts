<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Reacts\Events\ReactionCreated;
use Pharaonic\Laravel\Reacts\Events\ReactionRemoved;
use Pharaonic\Laravel\Reacts\Events\ReactionReplaced;
use Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\NoSelfReactionPolicy;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

const REACTS_EVENTS = [ReactionCreated::class, ReactionReplaced::class, ReactionRemoved::class];

test('created once per insert and never for a duplicate', function () {
    Event::fake(REACTS_EVENTS);
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
});

test('removed only when a row was deleted', function () {
    $user = User::create();
    $post = Post::create();
    Event::fake(REACTS_EVENTS);

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
});

test('single mode replacement is one replaced event', function () {
    $user = User::create();
    $post = SinglePost::create();
    Event::fake(REACTS_EVENTS);

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
});

test('events follow the write own transaction', function () {
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
});

test('a failed write dispatches nothing', function () {
    config(['pharaonic.reacts.policy' => NoSelfReactionPolicy::class]);
    $post = SinglePost::create();
    $user = User::create();
    $user->react($post, 'like');
    Event::fake(REACTS_EVENTS);

    try {
        $user->react($post, 'angry');
    } catch (ReactionNotAllowed $exception) {
        $this->assertSame(['like'], $user->reactionsOn($post)->pluck('type_key')->all());
    }

    Event::assertNotDispatched(ReactionReplaced::class);
    Event::assertNotDispatched(ReactionCreated::class);
});

test('cleanup on delete dispatches no removed event', function () {
    $user = User::create();
    $post = Post::create();
    $user->react($post, 'like');
    Event::fake(REACTS_EVENTS);

    $post->delete();

    $this->assertSame(0, Reaction::query()->count());
    Event::assertNotDispatched(ReactionRemoved::class);
});

test('a cancelled insert throws and dispatches nothing', function () {
    Reaction::creating(function () {
        return false;
    });
    Event::fake(REACTS_EVENTS);

    try {
        User::create()->react(Post::create(), 'like');
        $this->fail('A cancelled insert reported a change.');
    } catch (RuntimeException $exception) {
        $this->assertStringContainsString('cancelled saving the reaction', $exception->getMessage());
    }

    $this->assertSame(0, Reaction::query()->count());
    Event::assertNotDispatched(ReactionCreated::class);
});
