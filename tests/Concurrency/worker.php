<?php

/*
 * One worker of ConcurrencyTest: boots the package, waits for the shared
 * start time, then acts on one pair and prints the result as JSON.
 *
 * Usage: php worker.php <react|toggle> <post class> <user id> <post id> <type> <start time>
 */

use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Reacts\Events\ReactionCreated;
use Pharaonic\Laravel\Reacts\Events\ReactionRemoved;
use Pharaonic\Laravel\Reacts\Events\ReactionReplaced;
use Pharaonic\Laravel\Reacts\Tests\Concurrency\WorkerApplication;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

require __DIR__.'/../../vendor/autoload.php';

[, $action, $postClass, $userId, $postId, $type, $start] = $argv;

(new WorkerApplication('worker'))->createApplication();

$events = [];

foreach ([ReactionCreated::class, ReactionReplaced::class, ReactionRemoved::class] as $event) {
    Event::listen($event, function () use (&$events, $event) {
        $events[] = class_basename($event);
    });
}

$user = User::query()->findOrFail($userId);
$post = $postClass::query()->findOrFail($postId);

if ((float) $start > microtime(true)) {
    time_sleep_until((float) $start);
}

$result = $action === 'toggle' ? $user->toggleReaction($post, $type) : $user->react($post, $type);

echo json_encode(['result' => $result, 'events' => $events]);
