<?php

use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\NoSelfReactionPolicy;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

beforeEach(function () {
    NoSelfReactionPolicy::$calls = [];
    config(['pharaonic.reacts.policy' => NoSelfReactionPolicy::class]);
});

test('a refusal is reaction not allowed and writes nothing', function () {
    $user = User::create();

    foreach ([[$user, 'like'], [Post::create(), 'angry']] as [$target, $type]) {
        try {
            $user->react($target, $type);
            $this->fail('The policy was bypassed.');
        } catch (ReactionNotAllowed $exception) {
            $this->assertStringContainsString('does not allow', $exception->getMessage());
        }
    }

    $this->assertSame(0, Reaction::query()->count());
});

test('a refused replacement keeps the previous reaction', function () {
    $user = User::create();
    $post = SinglePost::create();
    $user->react($post, 'like');

    try {
        $user->react($post, 'angry');
        $this->fail('The policy was bypassed.');
    } catch (ReactionNotAllowed $exception) {
        $this->assertSame(['like'], $user->reactionsOn($post)->pluck('type_key')->all());
    }
});

test('the policy gets the definition and context and runs only when adding', function () {
    $this->requireEmojiProperties();

    $user = User::create();
    $message = ChatMessage::create();

    $user->react($message, 'love');
    $user->react($message, '🚀');
    $user->react($message, 'love');
    $user->toggleReaction($message, 'love');
    $user->unreact($message, '🚀');

    $this->assertSame([
        ['love', 'chat', 'multiple'],
        ['🚀', 'chat', 'multiple'],
    ], NoSelfReactionPolicy::$calls);
});

test('catalog validation comes before the policy', function () {
    $this->expectException(InvalidReactionType::class);

    try {
        User::create()->react(Post::create(), 'fire');
    } finally {
        $this->assertSame([], NoSelfReactionPolicy::$calls);
    }
});

test('a policy class must implement the contract', function () {
    config(['pharaonic.reacts.policy' => \stdClass::class]);

    $this->expectException(InvalidReactionConfiguration::class);
    User::create()->react(Post::create(), 'like');
});

test('a policy binding that resolves to something else refuses the reaction', function () {
    app()->bind(NoSelfReactionPolicy::class, fn () => new \stdClass);

    $this->expectException(ReactionNotAllowed::class);

    try {
        User::create()->react(Post::create(), 'like');
    } finally {
        $this->assertSame(0, Reaction::query()->count());
    }
});
