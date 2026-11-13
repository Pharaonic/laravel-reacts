<?php

use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\InvalidModePost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\Post;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\UnknownGroupPost;
use Pharaonic\Laravel\Reacts\Tests\Fixtures\User;

test('invalid settings fail clearly', function (array $settings, string $message) {
    foreach ($settings as $key => $value) {
        config(['pharaonic.reacts.'.$key => $value]);
    }

    $this->expectException(InvalidReactionConfiguration::class);
    $this->expectExceptionMessage($message);

    User::create()->react(Post::create(), 'like');
})->with([
    'mode' => [['mode' => 'both'], 'pharaonic.reacts.mode'],
    'mode type' => [['mode' => null], 'pharaonic.reacts.mode'],
    'model' => [['model' => \stdClass::class], 'pharaonic.reacts.model'],
    'table' => [['table' => 'bad name'], 'pharaonic.reacts.table'],
    'connection' => [['connection' => 'missing'], 'pharaonic.reacts.connection'],
    'policy' => [['policy' => \stdClass::class], 'pharaonic.reacts.policy'],
    'unknown parent' => [['groups.posts.extends' => 'nope'], 'extends'],
    'cycle' => [['groups.default.extends' => 'posts'], 'cycle'],
    'missing definition' => [['groups.posts.types' => ['love', 'zap']], '[zap]'],
    'bad key' => [['definitions.Bad' => ['label' => 'Bad']], 'not a valid type key'],
    'no label' => [['definitions.zap' => ['emoji' => '⚡']], 'needs a label'],
    'unknown definition field' => [['definitions.zap' => ['label' => 'Zap', 'colour' => 'x']], 'unknown fields'],
    'unknown group field' => [['groups.posts.except' => ['like']], 'unknown fields'],
    'default group' => [['default_group' => 'nope'], 'default_group'],
    'free form' => [['groups.posts.free_form' => 'yes'], 'free_form'],
    'unsafe icon' => [['definitions.zap' => ['label' => 'Zap', 'icon' => 'javascript:x']], 'icon'],
    'not an emoji' => [['definitions.zap' => ['label' => 'Zap', 'emoji' => 'zap']], 'emoji'],
    'meta list' => [['definitions.zap' => ['label' => 'Zap', 'meta' => [1, 2]]], 'meta'],
]);

test('the cleanup flag is validated on delete', function () {
    config(['pharaonic.reacts.cleanup_on_delete' => 'yes']);

    $this->expectException(InvalidReactionConfiguration::class);
    $this->expectExceptionMessage('cleanup_on_delete');
    Post::create()->delete();
});

test('model contract values are validated', function () {
    $cases = [
        'reactionMode()' => new InvalidModePost,
        'not configured' => new UnknownGroupPost,
    ];

    foreach ($cases as $message => $model) {
        $model->save();

        try {
            User::create()->react($model, 'like');
            $this->fail("$message was accepted.");
        } catch (InvalidReactionConfiguration $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }
});

test('the configuration is serializable for config cache', function () {
    $config = require __DIR__.'/../../config/reacts.php';

    $this->assertSame($config, eval('return '.var_export($config, true).';'));
});
