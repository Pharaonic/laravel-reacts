<?php

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Support\ReactionView;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

test('groups offer their own and inherited types in order', function () {
    $this->assertSame(['like', 'love'], groupChoices(Comment::create()));
    $this->assertSame(['like', 'love', 'laugh', 'wow', 'sad', 'angry'], groupChoices(Post::create()));
    $this->assertSame(['love', 'laugh', 'fire'], groupChoices(ChatMessage::create()));
});

test('free form comes from the nearest group that sets it', function () {
    config([
        'pharaonic.reacts.groups.posts.free_form' => true,
        'pharaonic.reacts.groups.support' => ['extends' => 'posts'],
    ]);

    $this->assertTrue(ReactionView::for(Post::create())->toArray()['free_form_enabled']);
    $this->assertFalse(ReactionView::for(Comment::create())->toArray()['free_form_enabled']);
    $this->assertTrue(ReactionView::for(ChatMessage::create())->toArray()['free_form_enabled']);
});

test('definitions carry their presentation', function () {
    config([
        'pharaonic.reacts.definitions.party_cat' => ['label' => 'Party Cat', 'icon' => '/reactions/party-cat.webp', 'meta' => ['animated' => true]],
        'pharaonic.reacts.groups.default.types' => ['like', 'party_cat'],
    ]);

    $choice = ReactionView::for(Comment::create())->toArray()['choices'][1];

    $this->assertSame(['registered', 'party_cat', 'Party Cat', null, '/reactions/party-cat.webp'], [
        $choice['kind'], $choice['key'], $choice['label'], $choice['emoji'], $choice['icon'],
    ]);
});

test('a type removed from a group stays counted and removable', function () {
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
});

/**
 * The keys a model's group offers, in order.
 *
 * @return list<string>
 */
function groupChoices(Model $model): array
{
    return array_column(ReactionView::for($model)->toArray()['choices'], 'key');
}
