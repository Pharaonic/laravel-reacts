<?php

use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Support\ReactionIdentity;

test('registered keys are lowercased', function () {
    $this->assertSame('love', ReactionIdentity::normalize('Love'));
    $this->assertSame('party_cat', ReactionIdentity::normalize('PARTY_CAT'));
});

test('emoji are canonicalized and text never becomes one', function () {
    $this->requireEmojiProperties();

    $this->assertSame("\u{1F680}", ReactionIdentity::normalize("\u{1F680}\u{FE0F}"));

    foreach (["\u{1F680}\u{1F525}", "love\n"] as $type) {
        try {
            ReactionIdentity::normalize($type);
            $this->fail('Accepted '.bin2hex($type));
        } catch (InvalidReactionType $exception) {
            $this->assertStringContainsString('neither a registered key nor exactly one emoji', $exception->getMessage());
        }
    }
});

test('invalid types are refused', function (string $type) {
    $this->expectException(InvalidReactionType::class);

    ReactionIdentity::normalize($type);
})->with([
    'empty' => [''],
    'space' => ['a b'],
    'leading digit' => ['1st'],
    'punctuation' => ['love!'],
    'too long' => [str_repeat('a', 65)],
]);

test('hashes are fixed width and depend on every part', function () {
    $reactor = ['user', '1'];
    $post = ['post', '1'];

    $pair = ReactionIdentity::pairHash($reactor, $post);
    $identity = ReactionIdentity::identityHash($reactor, $post, 'love');

    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $pair);
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $identity);
    $this->assertSame($pair, ReactionIdentity::pairHash($reactor, $post));
    $this->assertNotSame($pair, ReactionIdentity::pairHash($post, $reactor));
    $this->assertNotSame($identity, ReactionIdentity::identityHash($reactor, $post, 'like'));
    $this->assertNotSame($identity, ReactionIdentity::identityHash(['user', '2'], $post, 'love'));
    // The parts are encoded, never concatenated: "1" + "2" is not "12".
    $this->assertNotSame(ReactionIdentity::pairHash(['user', '1'], ['2post', '1']), ReactionIdentity::pairHash(['user', '12'], ['post', '1']));
});
