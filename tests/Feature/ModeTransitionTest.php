<?php

use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Reacts\Events\ReactionReplaced;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\StatefulPost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

/*
 * A reactable's mode changes after reactions were stored.
 */

test('reads never rewrite rows after a switch to single', function () {
    $user = User::create();
    $post = StatefulPost::create(['single' => false]);
    $user->react($post, 'like');
    $user->react($post, 'love');
    $post->update(['single' => true]);

    $this->assertTrue($user->hasReacted($post, 'like'));
    $this->assertCount(2, $user->reactionsOn($post));
    $this->assertSame(['like' => 1, 'love' => 1], $post->reactionCounts());
    $this->assertSame(2, Reaction::query()->count());
});

test('the next write in single mode reconciles the pair', function () {
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
});

test('repeating an existing type in single mode drops the others', function () {
    $user = User::create();
    $post = StatefulPost::create(['single' => false]);
    $user->react($post, 'like');
    $user->react($post, 'love');
    $post->update(['single' => true]);

    $this->assertTrue($user->react($post, 'love'));
    $this->assertSame(['love'], $user->reactionsOn($post)->pluck('type_key')->all());
    $this->assertNotNull(Reaction::query()->value('single_slot_hash'));
    $this->assertFalse($user->react($post, 'love'));
});

test('switching to multiple frees the slot before adding', function () {
    $user = User::create();
    $post = StatefulPost::create(['single' => true]);
    $user->react($post, 'like');
    $this->assertNotNull(Reaction::query()->value('single_slot_hash'));
    $post->update(['single' => false]);

    $this->assertTrue($user->react($post, 'love'));
    $this->assertSame(['like', 'love'], $user->reactionsOn($post)->pluck('type_key')->all());
    $this->assertSame(0, Reaction::query()->whereNotNull('single_slot_hash')->count());
});
