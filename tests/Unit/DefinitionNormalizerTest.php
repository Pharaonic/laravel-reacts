<?php

use Pharaonic\Laravel\Reacts\Catalog\DefinitionNormalizer;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;

test('a definition is completed and its emoji canonicalized', function () {
    $this->requireEmojiProperties();

    $this->assertSame(
        ['label' => 'Love', 'emoji' => "\u{2764}\u{FE0F}", 'icon' => null, 'meta' => []],
        DefinitionNormalizer::definition(['label' => 'Love', 'emoji' => "\u{2764}"], 'love')
    );
    $this->assertSame(
        ['label' => '<b>Bold</b>', 'emoji' => null, 'icon' => 'https://cdn.example.com/a.webp', 'meta' => ['animated' => true]],
        DefinitionNormalizer::definition(['label' => '<b>Bold</b>', 'icon' => 'https://cdn.example.com/a.webp', 'meta' => ['animated' => true]], 'bold')
    );
});

test('a definition emoji must be one emoji', function () {
    $this->requireEmojiProperties();

    $this->expectException(InvalidReactionType::class);
    $this->expectExceptionMessage('emoji');

    DefinitionNormalizer::definition(['label' => 'Cat', 'emoji' => "\u{1F431}\u{1F431}"], 'cat');
});

test('safe icon references are kept', function (string $icon) {
    $this->assertSame($icon, DefinitionNormalizer::definition(['label' => 'Cat', 'icon' => $icon], 'cat')['icon']);
})->with([
    'absolute path' => ['/storage/reactions/party-cat.webp'],
    'relative path' => ['reactions/party-cat.webp'],
    'path with query' => ['/icons/cat.svg?v=2'],
    'https' => ['https://cdn.example.com/cat.png'],
]);

test('unsafe icon references are refused', function (mixed $icon) {
    $this->expectException(InvalidReactionType::class);
    $this->expectExceptionMessage('icon');

    DefinitionNormalizer::definition(['label' => 'Cat', 'icon' => $icon], 'cat');
})->with([
    'javascript' => ['javascript:alert(1)'],
    'data' => ['data:image/png;base64,AAAA'],
    'http' => ['http://example.com/cat.png'],
    'protocol relative' => ['//evil.test/cat.png'],
    'credentials' => ['https://user:secret@example.com/cat.png'],
    'whitespace' => ['/a b.png'],
    'quote' => ['/a".png'],
    'backslash' => ['\\\\evil\\cat.png'],
    'empty' => [''],
    'too long' => ['/'.str_repeat('a', 1024)],
    'not a string' => [42],
]);

test('invalid definitions are refused', function (array $definition, string $message) {
    $this->expectException(InvalidReactionType::class);
    $this->expectExceptionMessage($message);

    DefinitionNormalizer::definition($definition, 'cat');
})->with([
    'no label' => [['emoji' => "\u{1F431}"], 'label'],
    'blank label' => [['label' => '  '], 'label'],
    'long label' => [['label' => str_repeat('x', 101)], 'label'],
    'control character' => [['label' => "a\nb"], 'label'],
    'text emoji' => [['label' => 'Cat', 'emoji' => 'cat'], 'emoji'],
    'list meta' => [['label' => 'Cat', 'meta' => [1, 2]], 'meta'],
    'scalar meta' => [['label' => 'Cat', 'meta' => 'x'], 'meta'],
    'unknown field' => [['label' => 'Cat', 'colour' => 'red'], 'unknown fields'],
]);
