<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\UlidModel;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\UuidModel;

test('relations eager loading and counts', function () {
    $user = User::create();
    $bot = Bot::create();
    [$first, $second] = [Post::create(), Post::create()];
    $user->react($first, 'like');
    $user->react($first, 'love');
    $bot->react($first, 'like');
    $bot->react($second, 'wow');

    $posts = Post::query()->withCount('reactions')->with('reactions.reactor')->orderBy('id')->get();

    $this->assertSame([3, 1], $posts->pluck('reactions_count')->map(function ($count) {
        return (int) $count;
    })->all());
    $reactors = $posts[0]->reactions->sortBy('id')->values()->map(function (Reaction $reaction) {
        return get_class($reaction->reactor);
    })->all();
    $this->assertSame([User::class, User::class, Bot::class], $reactors);
    $this->assertTrue($posts[1]->reactions[0]->reactable->is($second));
    $this->assertSame(2, $user->givenReactions()->count());
    $this->assertSame(2, $bot->givenReactions()->count());
    $this->assertInstanceOf(Post::class, $bot->givenReactions()->with('reactable')->orderBy('id')->firstOrFail()->reactable);
});

test('where reacted by with and without a type', function () {
    $user = User::create();
    [$liked, $loved, $none] = [Post::create(), Post::create(), Post::create()];
    $user->react($liked, 'like');
    $user->react($loved, 'love');
    Bot::create()->react($none, 'like');

    $this->assertSame([$liked->id, $loved->id], Post::whereReactedBy($user)->orderBy('id')->pluck('id')->all());
    $this->assertSame([$loved->id], Post::whereReactedBy($user, 'love')->pluck('id')->all());
    $this->assertSame([], Post::whereReactedBy(new User)->pluck('id')->all());
});

test('reaction scopes', function () {
    $user = User::create();
    $post = Post::create();
    $user->react($post, 'like');
    Bot::create()->react($post, 'love');

    $this->assertSame(1, Reaction::query()->whereReactor($user)->count());
    $this->assertSame(2, Reaction::query()->whereReactable($post)->count());
    $this->assertSame(1, Reaction::query()->whereType('LOVE')->count());
});

test('a model can use both traits', function () {
    [$alice, $bob] = [User::create(), User::create()];

    $alice->react($bob, 'like');
    $bob->react($alice, 'love');

    $this->assertSame(['like' => 1], $bob->reactionCounts());
    $this->assertSame(1, $alice->givenReactions()->count());
    $this->assertSame(1, $alice->reactions()->count());
    $this->assertSame([$bob->id], User::whereReactedBy($alice)->pluck('id')->all());
});

test('uuid and ulid keys', function () {
    $uuid = UuidModel::create();
    $ulid = UlidModel::create();

    $this->assertTrue($uuid->react($ulid, 'like'));
    $this->assertTrue($ulid->react($uuid, 'love'));
    $this->assertTrue($uuid->hasReacted($ulid, 'like'));
    $this->assertSame((string) $uuid->getKey(), Reaction::query()->where('type_key', 'like')->value('reactor_id'));
    $this->assertSame([$ulid->getKey()], UlidModel::whereReactedBy($uuid)->pluck('id')->all());
    $this->assertSame(1, (int) UuidModel::query()->withCount('reactions')->firstOrFail()->getAttribute('reactions_count'));
});

test('morph map aliases are stored', function () {
    Relation::morphMap(['user' => User::class, 'post' => Post::class]);
    $user = User::create();
    $post = Post::create();

    $user->react($post, 'like');

    $row = Reaction::query()->firstOrFail();
    $this->assertSame(['user', 'post'], [$row->reactor_type, $row->reactable_type]);
    $this->assertInstanceOf(User::class, $row->reactor);
    $this->assertTrue($user->hasReacted($post, 'like'));
    $this->assertSame([$post->id], Post::whereReactedBy($user)->pluck('id')->all());
});

test('counts are one aggregate query with stable order', function () {
    $this->requireEmojiProperties();

    $message = ChatMessage::create();

    foreach (['love', 'fire', 'laugh', '🚀', 'fire', 'laugh', '🚀'] as $index => $type) {
        User::create()->react($message, $type);
    }

    $queries = $this->queries(function () use ($message, &$counts, &$top) {
        $counts = $message->reactionCounts();
    });

    $this->assertCount(1, $queries);
    $this->assertSame(['fire' => 2, 'laugh' => 2, '🚀' => 2, 'love' => 1], $counts);
    $this->assertSame('fire', $message->topReaction());
    $this->assertSame([], ChatMessage::create()->reactionCounts());
    $this->assertNull(ChatMessage::create()->topReaction());
});

test('counts use a loaded relation', function () {
    $post = Post::create();
    User::create()->react($post, 'like');
    User::create()->react($post, 'like');
    $post = Post::query()->with('reactions')->findOrFail($post->id);

    $this->assertSame([], $this->queries(function () use ($post) {
        $this->assertSame(['like' => 2], $post->reactionCounts());
        $this->assertSame('like', $post->topReaction());
    }));
});

test('reactions on issues one query without eager loads', function () {
    $user = User::create();
    $post = Post::create();
    $user->react($post, 'like');

    $queries = $this->queries(function () use ($user, $post, &$reactions) {
        $reactions = $user->reactionsOn($post);
    });

    $this->assertCount(1, $queries);
    $this->assertSame([], $reactions[0]->getRelations());
});
