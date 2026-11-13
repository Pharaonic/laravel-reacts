<?php

use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Support\ReactionIdentity;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SingleChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\StatefulPost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

test('the documented single mode example', function () {
    $user = User::create();
    $post = SinglePost::create();

    $this->assertTrue($user->react($post, 'like'));
    $this->assertTrue($user->react($post, 'love'));
    $this->assertSame(1, Reaction::query()->count());
    $this->assertFalse($user->react($post, 'love'));
    $this->assertFalse($user->hasReacted($post, 'like'));
    $this->assertTrue($user->hasReacted($post, 'love'));
    $this->assertTrue($user->toggleReaction($post, 'wow'));
    $this->assertSame(['wow'], $user->reactionsOn($post)->pluck('type_key')->all());
    $this->assertFalse($user->toggleReaction($post, 'wow'));
    $this->assertSame(0, Reaction::query()->count());
});

test('the single mode row holds the pair slot', function () {
    $user = User::create();
    $post = SinglePost::create();
    $user->react($post, 'like');

    $pair = ReactionIdentity::pairHash([$user->getMorphClass(), (string) $user->getKey()], [$post->getMorphClass(), (string) $post->getKey()]);
    $this->assertSame($pair, Reaction::query()->value('single_slot_hash'));
    $this->assertSame($pair, Reaction::query()->value('pair_hash'));
});

test('unreact never removes another type', function () {
    $user = User::create();
    $post = SinglePost::create();
    $user->react($post, 'love');

    $this->assertFalse($user->unreact($post, 'like'));
    $this->assertTrue($user->hasReacted($post, 'love'));
});

test('free form emoji replace registered types too', function () {
    $this->requireEmojiProperties();

    $user = User::create();
    $message = SingleChatMessage::create();

    $this->assertTrue($user->react($message, 'love'));
    $this->assertTrue($user->react($message, '🚀'));
    $this->assertSame(['🚀'], $user->reactionsOn($message)->pluck('type_key')->all());
});

test('the mode belongs to the reactable not the reactor', function () {
    $user = User::create();
    $bot = Bot::create();
    $single = SinglePost::create();
    $multiple = Post::create();

    $user->react($single, 'like');
    $user->react($single, 'love');
    $bot->react($multiple, 'like');
    $bot->react($multiple, 'love');

    $this->assertSame(['love'], $user->reactionsOn($single)->pluck('type_key')->all());
    $this->assertSame(['like', 'love'], $bot->reactionsOn($multiple)->pluck('type_key')->all());
});

test('the global default can be single', function () {
    config(['pharaonic.reacts.mode' => 'single']);
    $user = User::create();
    $post = Post::create();

    $user->react($post, 'like');
    $user->react($post, 'love');

    $this->assertSame(['love'], $user->reactionsOn($post)->pluck('type_key')->all());
});

test('a mode from stored state is followed per instance', function () {
    $user = User::create();
    $single = StatefulPost::create(['single' => true]);
    $multiple = StatefulPost::create(['single' => false]);

    foreach ([$single, $multiple] as $post) {
        $user->react($post, 'like');
        $user->react($post, 'love');
    }

    $this->assertCount(1, $user->reactionsOn($single));
    $this->assertCount(2, $user->reactionsOn($multiple));
    $this->assertSame(['like' => 1, 'love' => 1], StatefulPost::query()->findOrFail($multiple->getKey())->reactionCounts());
});
