<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Reacts\Support\ReactionIdentity;
use Pharaonic\Laravel\Reacts\Support\ReactionView;
use Pharaonic\Laravel\Reacts\Support\ReactionViewBatch;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ChatMessage;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

test('the documented shape', function () {
    $viewer = User::create();
    $post = SinglePost::create();
    $viewer->react($post, 'love');
    User::create()->react($post, 'like');
    User::create()->react($post, 'like');

    $view = ReactionView::for($post, $viewer)->toArray();

    $this->assertSame(['mode', 'free_form_enabled', 'total', 'choices', 'used_other'], array_keys($view));
    $this->assertSame(['single', false, 3, []], [$view['mode'], $view['free_form_enabled'], $view['total'], $view['used_other']]);
    $this->assertSame(['like', 'love', 'laugh', 'wow', 'sad', 'angry'], array_column($view['choices'], 'key'));
    $this->assertSame([
        'kind' => 'registered', 'key' => 'love', 'label' => 'Love', 'emoji' => "\u{2764}\u{FE0F}", 'icon' => null,
        'count' => 1, 'selected' => true, 'selectable' => true,
    ], $view['choices'][1]);
    $this->assertSame([2, 1, 0, 0, 0, 0], array_column($view['choices'], 'count'));
    $this->assertSame([false, true, false, false, false, false], array_column($view['choices'], 'selected'));
    $this->assertSame($view, json_decode((string) json_encode($view), true));
});

test('anonymous viewers select nothing', function () {
    $post = Post::create();
    User::create()->react($post, 'like');

    foreach ([null, new User] as $viewer) {
        $view = ReactionView::for($post, $viewer)->toArray();

        $this->assertSame([], array_filter(array_column($view['choices'], 'selected')));
        $this->assertSame(1, $view['total']);
    }
});

test('used other lists history and free form emoji', function () {
    $this->requireEmojiProperties();

    $viewer = User::create();
    $message = ChatMessage::create();
    $viewer->react($message, '🚀');
    User::create()->react($message, '🚀');
    User::create()->react($message, 'fire');
    $viewer->react($message, 'laugh');

    // "laugh" leaves the group, and an imported row has a key nobody defines.
    config(['pharaonic.reacts.groups.chat.types' => ['love', 'fire']]);
    insertRawReaction($message, 'party_cat');

    $view = ReactionView::for($message, $viewer)->toArray();

    $this->assertSame(['love', 'fire'], array_column($view['choices'], 'key'));
    $this->assertSame(5, $view['total']);
    $this->assertSame([
        ['kind' => 'emoji', 'key' => '🚀', 'label' => '🚀', 'emoji' => '🚀', 'icon' => null, 'count' => 2, 'selected' => true, 'selectable' => true],
        ['kind' => 'registered', 'key' => 'laugh', 'label' => 'Laugh', 'emoji' => '😂', 'icon' => null, 'count' => 1, 'selected' => true, 'selectable' => false],
        ['kind' => 'registered', 'key' => 'party_cat', 'label' => 'Party cat', 'emoji' => null, 'icon' => null, 'count' => 1, 'selected' => false, 'selectable' => false],
    ], $view['used_other']);

    config(['pharaonic.reacts.groups.chat.free_form' => false]);
    $view = ReactionView::for($message, $viewer)->toArray();
    $this->assertFalse($view['free_form_enabled']);
    $this->assertFalse($view['used_other'][0]['selectable']);
});

test('a batch uses two queries whatever its size', function () {
    $viewer = User::create();
    $counts = [];

    foreach ([2, 30] as $size) {
        $models = [];

        for ($i = 0; $i < $size; $i++) {
            $models[] = $model = [Post::create(), ChatMessage::create(), Comment::create()][$i % 3];
            User::create()->react($model, 'love');
            $viewer->react($model, 'love');
        }

        $counts[$size] = count($this->queries(function () use ($models, $viewer) {
            ReactionView::forMany($models, $viewer);
        }));
    }

    $this->assertSame(2, $counts[2]);
    $this->assertSame(2, $counts[30]);
});

test('a batch finds each model by morph type and key', function () {
    Relation::morphMap(['post' => Post::class, 'comment' => Comment::class]);
    $viewer = User::create();
    $post = Post::create();
    $comment = Comment::create();
    $this->assertSame($post->getKey(), $comment->getKey());
    $viewer->react($post, 'wow');
    $viewer->react($comment, 'like');

    $batch = ReactionView::forMany(Post::query()->paginate(10)->getCollection()->concat([$comment]), $viewer);

    $this->assertInstanceOf(ReactionViewBatch::class, $batch);
    $this->assertSame(1, array_column($batch->get($post)['choices'], 'count', 'key')['wow']);
    $this->assertSame(['like' => 1, 'love' => 0], array_column($batch->get($comment)['choices'], 'count', 'key'));
    $this->assertTrue($batch->has(Comment::query()->findOrFail($comment->getKey())));
    $this->assertFalse($batch->has(Post::create()));

    $this->expectException(InvalidArgumentException::class);
    $batch->get(Post::create());
});

test('reading writes nothing', function () {
    $post = Post::create();
    User::create()->react($post, 'like');

    $queries = $this->queries(function () use ($post) {
        ReactionView::for($post, User::create())->toArray();
    });

    $this->assertSame([], array_values(array_filter($queries, function (string $query) {
        return (bool) preg_match('/^\s*(insert into "reaction|update|delete)/i', $query) && strpos($query, '"users"') === false;
    })));
});

/**
 * A row written outside the package, as an import would.
 */
function insertRawReaction(ChatMessage $message, string $key): void
{
    $reactor = [(new User)->getMorphClass(), '999'];
    $reactable = [$message->getMorphClass(), (string) $message->getKey()];

    DB::table('reactions')->insert([
        'reactor_type' => $reactor[0], 'reactor_id' => $reactor[1], 'reactable_type' => $reactable[0], 'reactable_id' => $reactable[1],
        'type_key' => $key,
        'identity_hash' => ReactionIdentity::identityHash($reactor, $reactable, $key),
        'pair_hash' => ReactionIdentity::pairHash($reactor, $reactable),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
