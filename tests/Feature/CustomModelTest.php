<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Reacts\Support\ReactionView;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\ExplicitReaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Schema as Fixtures;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SinglePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\SocialReaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;

/**
 * The README's model, table and connection override, end to end.
 */
class CustomModelTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.connections.secondary', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    public function test_the_model_and_table_are_used_everywhere(): void
    {
        config(['pharaonic.reacts.model' => SocialReaction::class, 'pharaonic.reacts.table' => 'social_reactions']);
        $this->freshDatabase();

        $this->assertTrue(Schema::hasTable('social_reactions'));
        $this->assertFalse(Schema::hasTable('reactions'));

        $user = User::create();
        $post = SinglePost::create();
        $user->react($post, 'like');
        $user->react($post, 'love');

        $this->assertInstanceOf(SocialReaction::class, $user->reactionsOn($post)->first());
        $this->assertInstanceOf(SocialReaction::class, $post->reactions()->first());
        $this->assertInstanceOf(SocialReaction::class, SinglePost::query()->with('reactions')->findOrFail($post->id)->reactions->first());
        $this->assertInstanceOf(SocialReaction::class, $user->givenReactions()->first());
        $this->assertSame(1, DB::table('social_reactions')->count());
        $this->assertSame(['love' => 1], $post->reactionCounts());
        $this->assertSame(1, ReactionView::for($post, $user)->toArray()['total']);
        $this->assertSame([$post->id], SinglePost::whereReactedBy($user)->pluck('id')->all());
    }

    public function test_the_configured_connection_holds_the_table(): void
    {
        config(['pharaonic.reacts.connection' => 'secondary']);
        $this->app->make(Kernel::class)->call('migrate:fresh');
        Fixtures::create();

        $this->assertTrue(Schema::connection('secondary')->hasTable('reactions'));
        $this->assertFalse(Schema::connection('testing')->hasTable('reactions'));

        $user = User::create();
        $post = Post::create();
        $user->react($post, 'like');

        $this->assertSame(1, DB::connection('secondary')->table('reactions')->count());
        $this->assertTrue($user->hasReacted($post, 'like'));
    }

    public function test_an_explicit_table_and_connection_win_over_the_settings(): void
    {
        config(['pharaonic.reacts.model' => ExplicitReaction::class, 'pharaonic.reacts.table' => 'ignored_reactions']);
        $this->app->make(Kernel::class)->call('migrate:fresh');
        Fixtures::create();

        $this->assertTrue(Schema::connection('secondary')->hasTable('explicit_reactions'));
        $this->assertFalse(Schema::connection('secondary')->hasTable('ignored_reactions'));

        User::create()->react(Post::create(), 'like');
        $this->assertSame(1, DB::connection('secondary')->table('explicit_reactions')->count());
    }
}
