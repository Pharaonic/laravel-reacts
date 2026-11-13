<?php

use Illuminate\Support\Carbon;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

/*
 * The README's multiple-mode example, and the rules around it.
 */

test('the documented post example', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->react($post, 'like'));
    $this->assertTrue($user->react($post, 'love'));
    $this->assertFalse($user->react($post, 'love'));
    $this->assertTrue($user->hasReacted($post));
    $this->assertTrue($user->hasReacted($post, 'love'));
    $this->assertTrue($user->unreact($post, 'love'));
    $this->assertFalse($user->unreact($post, 'love'));
    $this->assertFalse($user->toggleReaction($post, 'like'));

    $this->assertFalse($user->hasReacted($post));
    $this->assertSame(0, Reaction::query()->count());
});

test('distinct types coexist and other reactors are independent', function () {
    $user = User::create();
    $bot = Bot::create();
    $post = Post::create();

    $user->react($post, 'like');
    $user->react($post, 'love');
    $user->react($post, 'wow');
    $bot->react($post, 'like');

    $this->assertSame(['like', 'love', 'wow'], $user->reactionsOn($post)->pluck('type_key')->all());
    $this->assertSame(['like'], $bot->reactionsOn($post)->pluck('type_key')->all());
    $this->assertSame(4, Reaction::query()->count());
    $this->assertSame(0, Reaction::query()->whereNotNull('single_slot_hash')->count());
});

test('a duplicate touches nothing', function () {
    $user = User::create();
    $post = Post::create();
    $user->react($post, 'like');
    $before = Reaction::query()->firstOrFail();

    Carbon::setTestNow(Carbon::now()->addMinutes(5));
    $this->assertFalse($user->react($post, 'LIKE'));
    Carbon::setTestNow();

    $after = Reaction::query()->firstOrFail();
    $this->assertSame((string) $before->updated_at, (string) $after->updated_at);
    $this->assertSame(1, Reaction::query()->count());
});

test('registered keys are normalized to lowercase', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->react($post, 'Love'));
    $this->assertTrue($user->hasReacted($post, 'LOVE'));
    $this->assertSame('love', Reaction::query()->value('type_key'));
});

test('toggle returns the new state of the type', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->toggleReaction($post, 'wow'));
    $this->assertTrue($user->toggleReaction($post, 'sad'));
    $this->assertFalse($user->toggleReaction($post, 'wow'));
    $this->assertSame(['sad'], $user->reactionsOn($post)->pluck('type_key')->all());
});

test('clear returns how many were removed', function () {
    $user = User::create();
    $post = Post::create();
    $user->react($post, 'like');
    $user->react($post, 'love');
    $user->react($post, 'wow');
    Bot::create()->react($post, 'like');

    $this->assertSame(3, $user->clearReactions($post));
    $this->assertSame(0, $user->clearReactions($post));
    $this->assertSame(1, Reaction::query()->count());
});

test('the recipient side methods', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($post->reactBy($user, 'like'));
    $this->assertFalse($post->reactBy($user, 'like'));
    $this->assertTrue($post->reactedBy($user));
    $this->assertTrue($post->reactedBy($user, 'like'));
    $this->assertFalse($post->reactedBy($user, 'love'));
    $this->assertTrue($post->unreactBy($user, 'like'));
    $this->assertFalse($post->unreactBy($user, 'like'));
});

test('free form emoji only where the group allows it', function () {
    $this->requireEmojiProperties();

    $user = User::create();
    $message = ChatMessage::create();

    $this->assertTrue($user->react($message, 'love'));
    $this->assertTrue($user->react($message, '🚀'));
    $this->assertFalse($user->react($message, '🚀'));
    $this->assertTrue($user->hasReacted($message, '🚀'));
    $this->assertSame(['love' => 1, '🚀' => 1], $message->reactionCounts());

    $this->expectException(InvalidReactionType::class);
    $this->expectExceptionMessage('does not allow free-form emoji');
    $user->react(Post::create(), '🚀');
});

test('equivalent emoji spellings share one identity', function () {
    $this->requireEmojiProperties();

    $user = User::create();
    $message = ChatMessage::create();

    $this->assertTrue($user->react($message, "\u{2764}\u{FE0F}"));
    $this->assertFalse($user->react($message, "\u{2764}"));
    $this->assertTrue($user->react($message, '👍🏽'));
    $this->assertTrue($user->react($message, '👍🏿'));
    $this->assertSame(3, Reaction::query()->count());
    $this->assertTrue(Reaction::query()->where('type_key', "\u{2764}\u{FE0F}")->exists());
    $this->assertFalse($user->react($message, "\u{1F44D}\u{1F3FD}"));
    $this->assertTrue($user->react($message, '🚀'));
    $this->assertFalse($user->react($message, "\u{1F680}\u{FE0F}"));
    $this->assertTrue($user->hasReacted($message, "\u{1F680}\u{FE0F}"));
});

test('types the group does not offer are refused', function () {
    $user = User::create();

    foreach ([[Comment::create(), 'wow'], [Post::create(), 'fire'], [Post::create(), 'unknown']] as [$target, $type]) {
        try {
            $user->react($target, $type);
            $this->fail("[$type] was accepted.");
        } catch (InvalidReactionType $exception) {
            $this->assertStringContainsString('is not selectable', $exception->getMessage());
        }
    }

    $this->assertSame(0, Reaction::query()->count());
});

test('invalid type strings are refused and never stored as emoji', function () {
    $this->requireEmojiProperties();

    $user = User::create();
    $message = ChatMessage::create();

    foreach (['', ' ', 'love!', '1st', 'a b', str_repeat('a', 65), '🚀🔥', 'x🚀', '🏽', "\xff\xfe", '<b>', 'https://x.test'] as $type) {
        try {
            $user->react($message, $type);
            $this->fail('Accepted '.bin2hex($type));
        } catch (InvalidReactionType $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }

    $this->assertSame(0, Reaction::query()->count());
});

test('reads with unsaved models are false and writes throw', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertFalse((new User)->hasReacted($post));
    $this->assertFalse($user->hasReacted(new Post));
    $this->assertCount(0, $user->reactionsOn(new Post));

    foreach ([
        function () use ($post) {
            (new User)->react($post, 'like');
        },
        function () use ($user) {
            $user->react(new Post, 'like');
        },
        function () use ($user) {
            $user->unreact(new Post, 'like');
        },
        function () use ($user) {
            $user->clearReactions(new Post);
        },
    ] as $write) {
        try {
            $write();
            $this->fail('An unsaved model was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('is not saved', $exception->getMessage());
        }
    }
});

test('self reactions are allowed by default', function () {
    $user = User::create();

    $this->assertTrue($user->react($user, 'like'));
    $this->assertTrue($user->reactedBy($user, 'like'));
});
