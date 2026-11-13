<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Support;

use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;

/**
 * Typed, validated reads of the pharaonic.reacts settings.
 *
 * @internal
 */
final class Settings
{
    public const MODE_MULTIPLE = 'multiple';

    public const MODE_SINGLE = 'single';

    /**
     * Group and registered type keys.
     */
    public const KEY_PATTERN = '/^[a-z][a-z0-9_-]{0,63}$/D';

    /**
     * The longest free-form emoji accepted, in bytes.
     */
    public const MAXIMUM_EMOJI_BYTES = 128;

    public static function get(string $key, mixed $default = null): mixed
    {
        return Config::get('pharaonic.reacts.'.$key, $default);
    }

    /**
     * The default mode: pharaonic.reacts.mode.
     */
    public static function mode(): string
    {
        return self::assertMode(self::get('mode', self::MODE_MULTIPLE), 'The pharaonic.reacts.mode setting');
    }

    /**
     * @throws InvalidReactionConfiguration when the value is not "single" or "multiple"
     */
    public static function assertMode(mixed $mode, string $source): string
    {
        if ($mode !== self::MODE_MULTIPLE && $mode !== self::MODE_SINGLE) {
            throw new InvalidReactionConfiguration(sprintf(
                '%s must be "multiple" or "single", not %s.', $source, self::describe($mode)
            ));
        }

        return $mode;
    }

    public static function cleanupOnDelete(): bool
    {
        return self::bool('cleanup_on_delete', true);
    }

    public static function preserveOnSoftDelete(): bool
    {
        return self::bool('preserve_on_soft_delete', true);
    }

    public static function defaultGroup(): string
    {
        $group = self::get('default_group', 'default');

        if (! is_string($group) || preg_match(self::KEY_PATTERN, $group) !== 1) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.default_group setting must be a group key, not %s.', self::describe($group)
            ));
        }

        return $group;
    }

    /**
     * A value for an error message.
     */
    public static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return '"'.(mb_strlen($value) > 80 ? mb_substr($value, 0, 80).'…' : $value).'"';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_int($value) || is_float($value) ? (string) $value : get_debug_type($value);
    }

    private static function bool(string $key, bool $default): bool
    {
        $value = self::get($key, $default);

        if (! is_bool($value)) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.%s setting must be true or false, not %s.', $key, self::describe($value)
            ));
        }

        return $value;
    }
}
