<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\NoSelfReactionPolicy;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;

class PolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        NoSelfReactionPolicy::$calls = [];
        config(['pharaonic.reacts.policy' => NoSelfReactionPolicy::class]);
    }

    public function test_a_refusal_is_reaction_not_allowed_and_writes_nothing(): void
    {
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
    }

    public function test_a_refused_replacement_keeps_the_previous_reaction(): void
    {
        $user = User::create();
        $post = SinglePost::create();
        $user->react($post, 'like');

        try {
            $user->react($post, 'angry');
            $this->fail('The policy was bypassed.');
        } catch (ReactionNotAllowed $exception) {
            $this->assertSame(['like'], $user->reactionsOn($post)->pluck('type_key')->all());
        }
    }

    public function test_the_policy_gets_the_definition_and_context_and_runs_only_when_adding(): void
    {
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
    }

    public function test_catalog_validation_comes_before_the_policy(): void
    {
        $this->expectException(InvalidReactionType::class);

        try {
            User::create()->react(Post::create(), 'fire');
        } finally {
            $this->assertSame([], NoSelfReactionPolicy::$calls);
        }
    }

    public function test_a_policy_class_must_implement_the_contract(): void
    {
        config(['pharaonic.reacts.policy' => \stdClass::class]);

        $this->expectException(InvalidReactionConfiguration::class);
        User::create()->react(Post::create(), 'like');
    }
}
