<?php

namespace Pharaonic\Laravel\Reacts\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Reacts\Events\ReactionReplaced;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\StatefulPost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;
use Pharaonic\Laravel\Reacts\Tests\TestCase;

/**
 * A reactable's mode changes after reactions were stored.
 */
class ModeTransitionTest extends TestCase
{
    public function test_reads_never_rewrite_rows_after_a_switch_to_single(): void
    {
        $user = User::create();
        $post = StatefulPost::create(['single' => false]);
        $user->react($post, 'like');
        $user->react($post, 'love');
        $post->update(['single' => true]);

        $this->assertTrue($user->hasReacted($post, 'like'));
        $this->assertCount(2, $user->reactionsOn($post));
        $this->assertSame(['like' => 1, 'love' => 1], $post->reactionCounts());
        $this->assertSame(2, Reaction::query()->count());
    }

    public function test_the_next_write_in_single_mode_reconciles_the_pair(): void
    {
        $user = User::create();
        $post = StatefulPost::create(['single' => false]);
        $user->react($post, 'like');
        $user->react($post, 'love');
        $post->update(['single' => true]);
        Event::fake([ReactionReplaced::class]);

        // A new type replaces both old rows in one event.
        $this->assertTrue($user->react($post, 'wow'));
        $this->assertSame(['wow'], $user->reactionsOn($post)->pluck('type_key')->all());
        Event::assertDispatched(ReactionReplaced::class, function (ReactionReplaced $event) {
            return $event->current->type_key === 'wow' && array_map(function (Reaction $r) {
                return $r->type_key;
            }, $event->previous) === ['like', 'love'];
        });
    }

    public function test_repeating_an_existing_type_in_single_mode_drops_the_others(): void
    {
        $user = User::create();
        $post = StatefulPost::create(['single' => false]);
        $user->react($post, 'like');
        $user->react($post, 'love');
        $post->update(['single' => true]);

        $this->assertTrue($user->react($post, 'love'));
        $this->assertSame(['love'], $user->reactionsOn($post)->pluck('type_key')->all());
        $this->assertNotNull(Reaction::query()->value('single_slot_hash'));
        $this->assertFalse($user->react($post, 'love'));
    }

    public function test_switching_to_multiple_frees_the_slot_before_adding(): void
    {
        $user = User::create();
        $post = StatefulPost::create(['single' => true]);
        $user->react($post, 'like');
        $this->assertNotNull(Reaction::query()->value('single_slot_hash'));
        $post->update(['single' => false]);

        $this->assertTrue($user->react($post, 'love'));
        $this->assertSame(['like', 'love'], $user->reactionsOn($post)->pluck('type_key')->all());
        $this->assertSame(0, Reaction::query()->whereNotNull('single_slot_hash')->count());
    }
}
