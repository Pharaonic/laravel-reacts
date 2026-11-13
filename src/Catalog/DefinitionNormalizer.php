<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Catalog;

use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Support\Settings;
use Throwable;

/**
 * Validates a configured reaction type: label, emoji, icon, meta.
 *
 * The label is plain text up to 100 characters. The emoji is one emoji,
 * stored in canonical form. The icon is a relative path or an https URL of up
 * to 1024 characters, never javascript:, data: or another scheme. Meta is an
 * array with string keys.
 *
 * @internal
 */
final class DefinitionNormalizer
{
    private const FIELDS = ['label', 'emoji', 'icon', 'meta'];

    private const LABEL_LENGTH = 100;

    private const ICON_LENGTH = 1024;

    /**
     * @return array{label: string, emoji: string|null, icon: string|null, meta: array<string, mixed>}
     *
     * @throws InvalidReactionType when a field is missing, unknown, or invalid
     */
    public static function definition(mixed $definition, string $source): array
    {
        if (! is_array($definition)) {
            throw new InvalidReactionType(sprintf('%s must be an array, not %s.', $source, Settings::describe($definition)));
        }

        $unknown = array_diff(array_map('strval', array_keys($definition)), self::FIELDS);

        if ($unknown !== []) {
            throw new InvalidReactionType(sprintf('%s has unknown fields (%s); use %s.', $source, implode(', ', $unknown), implode(', ', self::FIELDS)));
        }

        return [
            'label' => self::label($definition['label'] ?? null, $source),
            'emoji' => self::emoji($definition['emoji'] ?? null, $source),
            'icon' => self::icon($definition['icon'] ?? null, $source),
            'meta' => self::meta($definition['meta'] ?? [], $source),
        ];
    }

    private static function label(mixed $label, string $source): string
    {
        if (! is_string($label) || ! mb_check_encoding($label, 'UTF-8') || trim($label) === ''
            || mb_strlen($label) > self::LABEL_LENGTH || preg_match('/[\x00-\x1F\x7F]/', $label) === 1) {
            throw new InvalidReactionType(sprintf(
                '%s needs a label of 1 to %d characters without control characters, not %s.', $source, self::LABEL_LENGTH, Settings::describe($label)
            ));
        }

        return $label;
    }

    private static function emoji(mixed $emoji, string $source): ?string
    {
        if ($emoji === null) {
            return null;
        }

        if (! is_string($emoji)) {
            $canonical = null;
        } elseif (EmojiValidator::isSupported()) {
            $canonical = EmojiValidator::canonicalize($emoji);
        } else {
            // Without PCRE's emoji properties, a configured emoji only needs to look like one:
            // no ASCII, no spaces or control characters. Configuration is trusted input.
            $canonical = $emoji !== '' && strlen($emoji) <= Settings::MAXIMUM_EMOJI_BYTES && mb_check_encoding($emoji, 'UTF-8')
                && preg_match('/[\x00-\x7F]|[\p{Z}\p{C}]/u', str_replace(["\u{FE0F}", "\u{200D}"], '', $emoji)) === 0 ? $emoji : null;
        }

        if ($canonical === null) {
            throw new InvalidReactionType(sprintf('%s emoji must be null or exactly one emoji, not %s.', $source, Settings::describe($emoji)));
        }

        return $canonical;
    }

    private static function icon(mixed $icon, string $source): ?string
    {
        if ($icon === null) {
            return null;
        }

        if (! is_string($icon) || $icon === '' || strlen($icon) > self::ICON_LENGTH || ! self::isSafeReference($icon)) {
            throw new InvalidReactionType(sprintf(
                '%s icon must be null, a relative path, or an https URL of up to %d characters, not %s.', $source, self::ICON_LENGTH, Settings::describe($icon)
            ));
        }

        return $icon;
    }

    /**
     * @return array<string, mixed>
     */
    private static function meta(mixed $meta, string $source): array
    {
        if (! is_array($meta) || ($meta !== [] && array_keys($meta) === range(0, count($meta) - 1))) {
            throw new InvalidReactionType(sprintf('%s meta must be an array with string keys, not %s.', $source, Settings::describe($meta)));
        }

        $object = [];

        foreach ($meta as $key => $value) {
            $object[(string) $key] = $value;
        }

        try {
            json_encode($object, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidReactionType(sprintf('%s meta cannot be encoded as JSON: %s', $source, $exception->getMessage()), 0, $exception);
        }

        return $object;
    }

    /**
     * A path on the application's own host, or an https URL. No other scheme,
     * no protocol-relative "//host", no backslashes, whitespace, quotes or control characters.
     */
    private static function isSafeReference(string $icon): bool
    {
        if (preg_match('/[\s\\\\\x00-\x1F\x7F<>"\'`]/', $icon) === 1 || str_starts_with($icon, '//')) {
            return false;
        }

        // A colon before the first "/", "?" or "#" starts a scheme.
        if (preg_match('#^[^/?\#]*:#', $icon) !== 1) {
            return true;
        }

        $parts = parse_url($icon);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && filter_var($icon, FILTER_VALIDATE_URL) !== false;
    }
}
