<?php

namespace Pharaonic\Laravel\Reacts\Tests\Unit;

use Pharaonic\Laravel\Reacts\Catalog\DefinitionNormalizer;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Tests\RequiresEmojiProperties;
use PHPUnit\Framework\TestCase;

class DefinitionNormalizerTest extends TestCase
{
    use RequiresEmojiProperties;

    public function test_a_definition_is_completed_and_its_emoji_canonicalized(): void
    {
        $this->requireEmojiProperties();

        $this->assertSame(
            ['label' => 'Love', 'emoji' => "\u{2764}\u{FE0F}", 'icon' => null, 'meta' => []],
            DefinitionNormalizer::definition(['label' => 'Love', 'emoji' => "\u{2764}"], 'love')
        );
        $this->assertSame(
            ['label' => '<b>Bold</b>', 'emoji' => null, 'icon' => 'https://cdn.example.com/a.webp', 'meta' => ['animated' => true]],
            DefinitionNormalizer::definition(['label' => '<b>Bold</b>', 'icon' => 'https://cdn.example.com/a.webp', 'meta' => ['animated' => true]], 'bold')
        );
    }

    public function test_a_definition_emoji_must_be_one_emoji(): void
    {
        $this->requireEmojiProperties();

        $this->expectException(InvalidReactionType::class);
        $this->expectExceptionMessage('emoji');

        DefinitionNormalizer::definition(['label' => 'Cat', 'emoji' => "\u{1F431}\u{1F431}"], 'cat');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function safeIcons(): array
    {
        return [
            'absolute path' => ['/storage/reactions/party-cat.webp'],
            'relative path' => ['reactions/party-cat.webp'],
            'path with query' => ['/icons/cat.svg?v=2'],
            'https' => ['https://cdn.example.com/cat.png'],
        ];
    }

    /**
     * @dataProvider safeIcons
     */
    public function test_safe_icon_references_are_kept(string $icon): void
    {
        $this->assertSame($icon, DefinitionNormalizer::definition(['label' => 'Cat', 'icon' => $icon], 'cat')['icon']);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function unsafeIcons(): array
    {
        return [
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
        ];
    }

    /**
     * @dataProvider unsafeIcons
     */
    public function test_unsafe_icon_references_are_refused(mixed $icon): void
    {
        $this->expectException(InvalidReactionType::class);
        $this->expectExceptionMessage('icon');

        DefinitionNormalizer::definition(['label' => 'Cat', 'icon' => $icon], 'cat');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public function invalidDefinitions(): array
    {
        return [
            'no label' => [['emoji' => "\u{1F431}"], 'label'],
            'blank label' => [['label' => '  '], 'label'],
            'long label' => [['label' => str_repeat('x', 101)], 'label'],
            'control character' => [['label' => "a\nb"], 'label'],
            'text emoji' => [['label' => 'Cat', 'emoji' => 'cat'], 'emoji'],
            'list meta' => [['label' => 'Cat', 'meta' => [1, 2]], 'meta'],
            'scalar meta' => [['label' => 'Cat', 'meta' => 'x'], 'meta'],
            'unknown field' => [['label' => 'Cat', 'colour' => 'red'], 'unknown fields'],
        ];
    }

    /**
     * @dataProvider invalidDefinitions
     *
     * @param  array<string, mixed>  $definition
     */
    public function test_invalid_definitions_are_refused(array $definition, string $message): void
    {
        $this->expectException(InvalidReactionType::class);
        $this->expectExceptionMessage($message);

        DefinitionNormalizer::definition($definition, 'cat');
    }
}
