<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Pharaonic\Laravel\Reacts\Catalog\EmojiValidator;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Support\ReactionView;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;

/**
 * On a PHP whose PCRE has no Unicode emoji properties (before PCRE2 10.40),
 * registered types keep working and only free-form emoji are refused.
 */
class FallbackWithoutEmojiPropertiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (EmojiValidator::isSupported()) {
            $this->markTestSkipped('This PHP has PCRE '.PCRE_VERSION.', which supports the emoji properties.');
        }
    }

    public function test_registered_types_and_their_emoji_keep_working(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->react($post, 'love'));
        $this->assertSame("\u{2764}\u{FE0F}", ReactionView::for($post, $user)->toArray()['choices'][1]['emoji']);
        $this->assertSame(['love' => 1], $post->reactionCounts());
    }

    public function test_configured_emoji_still_refuse_text(): void
    {
        config(['pharaonic.reacts.definitions.zap' => ['label' => 'Zap', 'emoji' => 'zap']]);

        $this->expectException(InvalidReactionConfiguration::class);
        $this->expectExceptionMessage('emoji');
        User::create()->react(Post::create(), 'like');
    }

    public function test_free_form_emoji_are_refused_with_the_reason(): void
    {
        $this->expectException(InvalidReactionConfiguration::class);
        $this->expectExceptionMessage('PCRE2 10.40');

        User::create()->react(ChatMessage::create(), "\u{1F680}");
    }
}
