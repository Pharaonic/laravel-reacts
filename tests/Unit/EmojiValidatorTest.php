<?php

use Pharaonic\Laravel\Reacts\Catalog\EmojiValidator;

beforeEach(function () {
    $this->requireEmojiProperties();
});

test('every spelling has one canonical form', function (string $input, string $canonical) {
    $this->assertSame(bin2hex($canonical), bin2hex((string) EmojiValidator::canonicalize($input)));
    $this->assertSame(bin2hex($canonical), bin2hex((string) EmojiValidator::canonicalize($canonical)));
})->with([
    'emoji style' => ["\u{1F680}", "\u{1F680}"],
    'emoji style with FE0F' => ["\u{1F680}\u{FE0F}", "\u{1F680}"],
    'text style' => ["\u{2764}", "\u{2764}\u{FE0F}"],
    'text style with FE0F' => ["\u{2764}\u{FE0F}", "\u{2764}\u{FE0F}"],
    'copyright' => ["\u{00A9}", "\u{00A9}\u{FE0F}"],
    'skin tone' => ["\u{1F44D}\u{1F3FD}", "\u{1F44D}\u{1F3FD}"],
    'flag' => ["\u{1F1EA}\u{1F1EC}", "\u{1F1EA}\u{1F1EC}"],
    'subdivision flag' => ["\u{1F3F4}\u{E0067}\u{E0062}\u{E0077}\u{E006C}\u{E0073}\u{E007F}", "\u{1F3F4}\u{E0067}\u{E0062}\u{E0077}\u{E006C}\u{E0073}\u{E007F}"],
    'keycap' => ["#\u{FE0F}\u{20E3}", "#\u{FE0F}\u{20E3}"],
    'keycap without FE0F' => ["#\u{20E3}", "#\u{FE0F}\u{20E3}"],
    'family' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}", "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}"],
    'profession with skin tone' => ["\u{1F469}\u{1F3FD}\u{200D}\u{1F4BB}", "\u{1F469}\u{1F3FD}\u{200D}\u{1F4BB}"],
    'heart on fire' => ["\u{2764}\u{200D}\u{1F525}", "\u{2764}\u{FE0F}\u{200D}\u{1F525}"],
]);

test('anything but one emoji is refused', function (string $input) {
    $this->assertNull(EmojiValidator::canonicalize($input));
})->with([
    'empty' => [''],
    'text' => ['love'],
    'digit' => ['1'],
    'digit with FE0F' => ["1\u{FE0F}"],
    'two emoji' => ["\u{1F680}\u{1F525}"],
    'text and emoji' => ["x\u{1F680}"],
    'emoji and space' => ["\u{1F680} "],
    'lone skin tone' => ["\u{1F3FD}"],
    'lone regional indicator' => ["\u{1F1EA}"],
    'leading zero width joiner' => ["\u{200D}\u{1F680}"],
    'trailing zero width joiner' => ["\u{1F680}\u{200D}"],
    'markup' => ['<b>'],
    'url' => ['https://example.com'],
    'invalid utf-8' => ["\xF0\x9F\x9A"],
    'too long' => [str_repeat("\u{1F468}\u{200D}", 40)."\u{1F468}"],
]);
