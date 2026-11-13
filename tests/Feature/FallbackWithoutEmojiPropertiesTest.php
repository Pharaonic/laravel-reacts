<?php

use Pharaonic\Laravel\Reacts\Catalog\EmojiValidator;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Support\ReactionView;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

/*
 * On a PHP whose PCRE has no Unicode emoji properties (before PCRE2 10.40),
 * registered types keep working and only free-form emoji are refused.
 */

beforeEach(function () {
    if (EmojiValidator::isSupported()) {
        $this->markTestSkipped('This PHP has PCRE '.PCRE_VERSION.', which supports the emoji properties.');
    }
});

test('registered types and their emoji keep working', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->react($post, 'love'));
    $this->assertSame("\u{2764}\u{FE0F}", ReactionView::for($post, $user)->toArray()['choices'][1]['emoji']);
    $this->assertSame(['love' => 1], $post->reactionCounts());
});

test('configured emoji still refuse text', function () {
    config(['pharaonic.reacts.definitions.zap' => ['label' => 'Zap', 'emoji' => 'zap']]);

    $this->expectException(InvalidReactionConfiguration::class);
    $this->expectExceptionMessage('emoji');
    User::create()->react(Post::create(), 'like');
});

test('free form emoji are refused with the reason', function () {
    $this->expectException(InvalidReactionConfiguration::class);
    $this->expectExceptionMessage('PCRE2 10.40');

    User::create()->react(ChatMessage::create(), "\u{1F680}");
});
