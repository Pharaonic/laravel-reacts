<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Support;

use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Models\Reaction;

/**
 * The configured reaction model, table and connection.
 *
 * @internal
 */
final class ReactionModelResolver
{
    private const TABLE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D';

    /**
     * @return class-string<Reaction>
     *
     * @throws InvalidReactionConfiguration when pharaonic.reacts.model is not Reaction or a subclass
     */
    public static function reactionClass(): string
    {
        $class = Settings::get('model');

        if ($class === null || $class === '') {
            return Reaction::class;
        }

        if (! is_string($class) || ! class_exists($class) || ! is_a($class, Reaction::class, true)) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.model setting must name %s or a subclass of it, not %s.',
                Reaction::class, Settings::describe($class)
            ));
        }

        return $class;
    }

    public static function reaction(): Reaction
    {
        $class = self::reactionClass();

        return new $class;
    }

    /**
     * The table the base model uses when its subclass sets no $table.
     *
     * @throws InvalidReactionConfiguration when the setting is not a plain table name
     */
    public static function table(): string
    {
        $table = Settings::get('table', 'reactions');

        if ($table === null) {
            return 'reactions';
        }

        if (! is_string($table) || preg_match(self::TABLE_PATTERN, $table) !== 1) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.table setting must be a table name (letters, digits and "_", up to 64), not %s.',
                Settings::describe($table)
            ));
        }

        return $table;
    }

    /**
     * The connection the base model uses when its subclass sets no $connection.
     *
     * @throws InvalidReactionConfiguration when it names no configured connection
     */
    public static function connection(): ?string
    {
        $connection = Settings::get('connection');

        if ($connection === null) {
            return null;
        }

        if (! is_string($connection) || $connection === '' || ! is_array(Config::get('database.connections.'.$connection))) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.connection setting must be null or a connection in database.connections, not %s.',
                Settings::describe($connection)
            ));
        }

        return $connection;
    }
}
