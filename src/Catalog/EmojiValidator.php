<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Catalog;

use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Support\Settings;

/**
 * Accepts exactly one emoji sequence and returns its canonical form.
 *
 * A sequence follows the UTS #51 grammar: one element, or elements joined by
 * U+200D (ZWJ). An element is a flag (two regional indicators), a keycap
 * ([0-9#*] U+FE0F U+20E3), a tag sequence (U+1F3F4, tags, U+E007F), or an
 * emoji with an optional U+FE0F or skin-tone modifier. Text, several emoji,
 * a lone modifier or regional indicator, and invalid UTF-8 are refused.
 *
 * Canonical form: an emoji shown as emoji by default ("🚀") never carries
 * U+FE0F, one shown as text by default ("❤") always does, and a keycap has it
 * before U+20E3, so every spelling of an emoji stores one key. The Unicode data is the PCRE library's, which needs
 * PCRE2 10.40 or later for the emoji properties.
 *
 * @internal
 */
final class EmojiValidator
{
    private const ELEMENT = '(?:'
        .'\p{Regional_Indicator}{2}'
        .'|[0-9#*]\x{FE0F}?\x{20E3}'
        .'|\x{1F3F4}[\x{E0020}-\x{E007E}]+\x{E007F}'
        .'|(?![0-9#*]|\p{Regional_Indicator}|\p{Emoji_Modifier})(?:\p{Emoji}|\p{Extended_Pictographic})(?:\x{FE0F}|\p{Emoji_Modifier})?'
        .')';

    private static ?bool $supported = null;

    /**
     * The canonical emoji, or null when the value is not exactly one emoji sequence.
     *
     * @throws InvalidReactionConfiguration when PCRE has no Unicode emoji properties
     */
    public static function canonicalize(string $value): ?string
    {
        if ($value === '' || strlen($value) > Settings::MAXIMUM_EMOJI_BYTES || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        self::assertSupported();

        if (preg_match('/^'.self::ELEMENT.'(?:\x{200D}'.self::ELEMENT.')*$/uD', $value) !== 1) {
            return null;
        }

        $elements = explode("\u{200D}", $value);

        foreach ($elements as $index => $element) {
            $elements[$index] = self::qualify($element);
        }

        return implode("\u{200D}", $elements);
    }

    /**
     * Whether this PHP's PCRE knows the Unicode emoji properties.
     */
    public static function isSupported(): bool
    {
        if (self::$supported === null) {
            self::$supported = @preg_match('/\p{Extended_Pictographic}\p{Emoji_Modifier}?/u', "\u{1F680}") === 1;
        }

        return self::$supported;
    }

    /**
     * One element in canonical form.
     */
    private static function qualify(string $element): string
    {
        // Keycap: the digit, U+FE0F, U+20E3.
        if (preg_match('/^([0-9#*])\x{FE0F}?\x{20E3}$/uD', $element, $match) === 1) {
            return $match[1]."\u{FE0F}\u{20E3}";
        }

        // An emoji alone, with or without U+FE0F: emoji-style ones drop it, text-style ones get it.
        if (preg_match('/^(.)\x{FE0F}?$/uD', $element, $match) === 1 && preg_match('/^(?:\p{Emoji}|\p{Extended_Pictographic})$/uD', $match[1]) === 1) {
            return preg_match('/^\p{Emoji_Presentation}$/uD', $match[1]) === 1 ? $match[1] : $match[1]."\u{FE0F}";
        }

        // Flags, tag sequences and skin-tone sequences are already canonical.
        return $element;
    }

    /**
     * @throws InvalidReactionConfiguration when PCRE has no Unicode emoji properties
     */
    private static function assertSupported(): void
    {
        if (! self::isSupported()) {
            throw new InvalidReactionConfiguration(sprintf(
                'Free-form emoji reactions need PCRE2 10.40 or later for its Unicode emoji properties; this PHP uses PCRE %s.',
                PCRE_VERSION
            ));
        }
    }
}
