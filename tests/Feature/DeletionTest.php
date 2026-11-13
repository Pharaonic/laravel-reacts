<?php

use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\NoSelfReactionPolicy;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SoftDeletableMember;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SoftDeletablePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

test('deleting removes given and received reactions', function () {
    [$alice, $bob] = [User::create(), User::create()];
    $post = Post::create();
    $alice->react($post, 'like');
    $alice->react($bob, 'like');
    $bob->react($alice, 'love');
    $bob->react($post, 'love');

    $alice->delete();

    $this->assertSame(['love'], Reaction::query()->pluck('type_key')->all());
    $this->assertTrue($bob->hasReacted($post, 'love'));
});

test('a policy cannot block cleanup', function () {
    config(['pharaonic.reacts.policy' => NoSelfReactionPolicy::class]);
    $user = User::create();
    $post = Post::create();
    $user->react($post, 'like');

    $post->delete();

    $this->assertSame(0, Reaction::query()->count());
});

test('soft deletes keep reactions and force deletes remove them', function () {
    $user = User::create();
    $post = SoftDeletablePost::create();
    $member = SoftDeletableMember::create();
    $user->react($post, 'like');
    $member->react($post, 'love');
    $user->react($member, 'like');

    $post->delete();
    $member->delete();
    $this->assertSame(3, Reaction::query()->count());

    $post->restore();
    $this->assertSame(['like' => 1, 'love' => 1], $post->reactionCounts());

    $member->forceDelete();
    $this->assertSame(['like'], Reaction::query()->pluck('type_key')->all());

    $post->forceDelete();
    $this->assertSame(0, Reaction::query()->count());
});

test('soft deletes can remove reactions', function () {
    config(['pharaonic.reacts.preserve_on_soft_delete' => false]);
    $post = SoftDeletablePost::create();
    User::create()->react($post, 'like');

    $post->delete();

    $this->assertSame(0, Reaction::query()->count());
});

test('cleanup can be turned off', function () {
    config(['pharaonic.reacts.cleanup_on_delete' => false]);
    $post = Post::create();
    User::create()->react($post, 'like');

    $post->delete();

    $this->assertSame(1, Reaction::query()->count());
});

test('mass deletes bypass cleanup and the documented query removes the rest', function () {
    $posts = [Post::create(), Post::create()];
    $user = User::create();
    $user->react($posts[0], 'like');
    $user->react($posts[1], 'like');

    Post::query()->whereKey([$posts[0]->id, $posts[1]->id])->delete();
    $this->assertSame(2, Reaction::query()->count());

    Reaction::query()
        ->where('reactable_type', (new Post)->getMorphClass())
        ->whereIn('reactable_id', [(string) $posts[0]->id, (string) $posts[1]->id])
        ->delete();
    $this->assertSame(0, Reaction::query()->count());
});
