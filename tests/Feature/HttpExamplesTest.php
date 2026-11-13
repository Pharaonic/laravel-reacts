<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Support\ReactionView;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\PostReactionsController;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

/*
 * The README's host-application examples. The package itself has no routes.
 */

beforeEach(function () {
    Route::middleware('web')->group(function () {
        Route::get('/posts', [PostReactionsController::class, 'index'])->name('posts.index');
        Route::get('/posts/{post}', [PostReactionsController::class, 'show'])->name('posts.show');
        Route::post('/posts/{post}/reactions/toggle', [PostReactionsController::class, 'toggle'])
            ->middleware('auth')->name('posts.reactions.toggle');
    });

    Route::middleware('api')->group(function () {
        Route::get('/api/posts/{post}/reactions', function (Post $post, Request $request) {
            return response()->json(['data' => ReactionView::for($post, $request->user())->toArray()]);
        });
        Route::post('/api/posts/{post}/reactions', function (Post $post, Request $request) {
            Gate::authorize('react', $post);
            $input = $request->validate(['type' => ['required', 'string', 'max:128']]);
            $request->user()->toggleReaction($post, $input['type']);

            return response()->json(['data' => ReactionView::for($post, $request->user())->toArray()]);
        })->middleware('auth');
    });

    Route::getRoutes()->refreshNameLookups();
    Gate::define('react', function (User $user, Post $post) {
        return $user->name !== 'banned';
    });
    config(['auth.providers.users.model' => User::class]);
});

test('the blade page lists choices counts and selection', function () {
    $viewer = User::create(['name' => 'viewer']);
    $post = Post::create(['name' => '<script>alert(1)</script>']);
    $viewer->react($post, 'love');
    User::create()->react($post, 'love');

    $html = $this->actingAs($viewer)->get('/posts/'.$post->id)->assertOk()->getContent();

    $this->assertIsString($html);
    $this->assertStringContainsString('Total reactions: 2', $html);
    $this->assertStringContainsString('Love (2)', $html);
    $this->assertStringContainsString('Wow (0)', $html);
    $this->assertSame(1, substr_count($html, 'aria-pressed="true"'));
    $this->assertStringContainsString('name="_token"', $html);
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
    $this->assertStringNotContainsString('free-form-emoji', $html);
});

test('anonymous viewers see disabled buttons', function () {
    $post = Post::create();
    User::create()->react($post, 'like');

    $html = (string) $this->get('/posts/'.$post->id)->assertOk()->getContent();

    $this->assertSame(0, substr_count($html, 'aria-pressed="true"'));
    $this->assertSame(6, substr_count($html, 'disabled'));
});

test('icons and history render escaped', function () {
    config([
        'pharaonic.reacts.definitions.party_cat' => ['label' => 'Party "Cat"', 'icon' => '/reactions/party-cat.webp'],
        'pharaonic.reacts.groups.posts.types' => ['laugh', 'party_cat'],
    ]);
    $post = Post::create();
    User::create()->react($post, 'party_cat');
    User::create()->react($post, 'laugh');
    config(['pharaonic.reacts.groups.posts.types' => ['party_cat']]);

    $html = (string) $this->get('/posts/'.$post->id)->getContent();

    $this->assertStringContainsString('<img src="/reactions/party-cat.webp"', $html);
    $this->assertStringContainsString('Party &quot;Cat&quot; (1)', $html);
    $this->assertStringContainsString('<span class="used-other">😂 (1)</span>', $html);
});

test('the toggle route adds removes and is authorized', function () {
    $user = User::create(['name' => 'member']);
    $post = Post::create();

    $this->actingAs($user)->from('/posts/'.$post->id)->post('/posts/'.$post->id.'/reactions/toggle', ['type' => 'love'])
        ->assertRedirect('/posts/'.$post->id);
    $this->assertTrue($user->hasReacted($post, 'love'));

    $this->actingAs($user)->post('/posts/'.$post->id.'/reactions/toggle', ['type' => 'love']);
    $this->assertFalse($user->hasReacted($post, 'love'));

    $banned = User::create(['name' => 'banned']);
    $this->actingAs($banned)->post('/posts/'.$post->id.'/reactions/toggle', ['type' => 'love'])->assertForbidden();
    $this->assertFalse($banned->hasReacted($post));

    $this->actingAs($user)->post('/posts/'.$post->id.'/reactions/toggle', [])->assertSessionHasErrors('type');
});

test('guests cannot toggle', function () {
    $post = Post::create();
    $this->app['router']->get('/login', function () {
        return 'login';
    })->name('login');
    Route::getRoutes()->refreshNameLookups();

    $this->post('/posts/'.$post->id.'/reactions/toggle', ['type' => 'love'])->assertRedirect('/login');
    $this->assertSame([], $post->reactionCounts());
});

test('an invalid type from a form is an exception for the application', function () {
    $this->withoutExceptionHandling();
    $user = User::create();
    $post = Post::create();

    $this->expectException(InvalidReactionType::class);
    $this->actingAs($user)->post('/posts/'.$post->id.'/reactions/toggle', ['type' => '<b>hi</b>']);
});

test('the json api payload', function () {
    $viewer = User::create();
    $post = Post::create();
    $viewer->react($post, 'wow');
    User::create()->react($post, 'like');

    $anonymous = $this->getJson('/api/posts/'.$post->id.'/reactions')->assertOk()->json('data');
    $this->assertSame(['mode', 'free_form_enabled', 'total', 'choices', 'used_other'], array_keys($anonymous));
    $this->assertSame([], array_filter(array_column($anonymous['choices'], 'selected')));
    // Counts only: no reactor ids or types.
    $this->assertStringNotContainsString('reactor', (string) json_encode($anonymous));
    $this->assertStringNotContainsString(addslashes(User::class), (string) json_encode($anonymous));

    $signedIn = $this->actingAs($viewer)->getJson('/api/posts/'.$post->id.'/reactions')->json('data');
    $this->assertSame(['wow'], array_values(array_column(array_filter($signedIn['choices'], function ($choice) {
        return $choice['selected'];
    }), 'key')));

    $after = $this->actingAs($viewer)->postJson('/api/posts/'.$post->id.'/reactions', ['type' => 'like'])->assertOk()->json('data');
    $this->assertSame(2, array_column($after['choices'], 'count', 'key')['like']);
});

test('the paginated feed', function () {
    $user = User::create();

    foreach (range(1, 3) as $number) {
        $user->react(Post::create(['name' => 'Post '.$number]), 'like');
    }

    $html = (string) $this->get('/posts')->assertOk()->getContent();

    $this->assertSame(3, substr_count($html, '👍: 1'));
    $this->assertStringContainsString('Post 3', $html);
});
