<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Support\ReactionView;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;

class GroupsTest extends TestCase
{
    public function test_groups_offer_their_own_and_inherited_types_in_order(): void
    {
        $this->assertSame(['like', 'love'], $this->choices(Comment::create()));
        $this->assertSame(['like', 'love', 'laugh', 'wow', 'sad', 'angry'], $this->choices(Post::create()));
        $this->assertSame(['love', 'laugh', 'fire'], $this->choices(ChatMessage::create()));
    }

    public function test_free_form_comes_from_the_nearest_group_that_sets_it(): void
    {
        config([
            'pharaonic.reacts.groups.posts.free_form' => true,
            'pharaonic.reacts.groups.support' => ['extends' => 'posts'],
        ]);

        $this->assertTrue(ReactionView::for(Post::create())->toArray()['free_form_enabled']);
        $this->assertFalse(ReactionView::for(Comment::create())->toArray()['free_form_enabled']);
        $this->assertTrue(ReactionView::for(ChatMessage::create())->toArray()['free_form_enabled']);
    }

    public function test_definitions_carry_their_presentation(): void
    {
        config([
            'pharaonic.reacts.definitions.party_cat' => ['label' => 'Party Cat', 'icon' => '/reactions/party-cat.webp', 'meta' => ['animated' => true]],
            'pharaonic.reacts.groups.default.types' => ['like', 'party_cat'],
        ]);

        $choice = ReactionView::for(Comment::create())->toArray()['choices'][1];

        $this->assertSame(['registered', 'party_cat', 'Party Cat', null, '/reactions/party-cat.webp'], [
            $choice['kind'], $choice['key'], $choice['label'], $choice['emoji'], $choice['icon'],
        ]);
    }

    public function test_a_type_removed_from_a_group_stays_counted_and_removable(): void
    {
        $user = User::create();
        $comment = Comment::create();
        $user->react($comment, 'love');

        config(['pharaonic.reacts.groups.default.types' => ['like']]);

        $this->assertTrue($user->hasReacted($comment, 'love'));
        $this->assertSame(['love' => 1], $comment->reactionCounts());

        try {
            $user->react($comment, 'love');
            $this->fail('A type the group no longer offers was accepted.');
        } catch (InvalidReactionType $exception) {
            $this->assertStringContainsString('not selectable', $exception->getMessage());
        }

        $this->assertFalse($user->toggleReaction($comment, 'love'));
        $this->assertSame([], $comment->reactionCounts());
    }

    /**
     * @return list<string>
     */
    private function choices(object $model): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        return array_column(ReactionView::for($model)->toArray()['choices'], 'key');
    }
}
