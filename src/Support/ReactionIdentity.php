<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Pharaonic\Laravel\Reacts\Catalog\EmojiValidator;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Stringable;

/**
 * Reaction identities: the canonical key of a type string, the
 * stored endpoints of a model, and the SHA-256 pair and identity hashes.
 *
 * @internal
 */
final class ReactionIdentity
{
    /**
     * The longest morph type the reactions table stores.
     */
    public const TYPE_LENGTH = 191;

    /**
     * The longest model key the reactions table stores.
     */
    public const KEY_LENGTH = 64;

    private const JSON = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * The canonical key of a type string, without consulting the catalog.
     *
     * ASCII input is a registered key (lowercased); anything else must be one
     * emoji. Unknown text never becomes a free-form reaction.
     *
     * @throws InvalidReactionType when the value is neither
     */
    public static function normalize(string $type): string
    {
        if ($type !== '' && preg_match('/^[\x20-\x7E]+$/D', $type) === 1) {
            $key = strtolower($type);

            if (preg_match(Settings::KEY_PATTERN, $key) !== 1) {
                throw new InvalidReactionType(sprintf(
                    'The reaction type %s is not a valid key: use a lowercase letter followed by up to 63 letters, digits, "_" or "-".',
                    Settings::describe($type)
                ));
            }

            return $key;
        }

        $emoji = EmojiValidator::canonicalize($type);

        if ($emoji === null) {
            throw new InvalidReactionType(sprintf(
                'The reaction type %s is neither a registered key nor exactly one emoji.',
                mb_check_encoding($type, 'UTF-8') ? Settings::describe($type) : 'with invalid UTF-8'
            ));
        }

        return $emoji;
    }

    /**
     * The morph type and key the reactions table stores for the model.
     *
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException when the model is unsaved, or its type or key does not fit
     */
    public static function endpoint(Model $model, string $side): array
    {
        if (! $model->exists) {
            throw new InvalidArgumentException(sprintf(
                'The %s [%s] is not saved: save it before it reacts or is reacted to.', $side, get_class($model)
            ));
        }

        $key = self::key($model);

        if ($key === null) {
            throw new InvalidArgumentException(sprintf('The %s [%s] has no usable primary key.', $side, get_class($model)));
        }

        if (mb_strlen($key) > self::KEY_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The key of the %s [%s] is %d characters long; the reactions table stores keys of up to %d characters.',
                $side, get_class($model), mb_strlen($key), self::KEY_LENGTH
            ));
        }

        $type = $model->getMorphClass();

        if (mb_strlen($type) > self::TYPE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The morph type of the %s [%s] is %d characters long; the reactions table stores up to %d. '
                .'Register a shorter alias with Relation::morphMap().',
                $side, get_class($model), mb_strlen($type), self::TYPE_LENGTH
            ));
        }

        return [$type, $key];
    }

    /**
     * Whether the model is saved and has a usable key.
     */
    public static function isPersisted(Model $model): bool
    {
        return $model->exists && self::key($model) !== null;
    }

    /**
     * The model key as the string the reactions table stores, or null.
     */
    public static function key(Model $model): ?string
    {
        return self::keyToString($model->getKey());
    }

    /**
     * A key as the string the reactions table stores, or null when it is not a usable key.
     */
    public static function keyToString(mixed $key): ?string
    {
        if (! is_int($key) && ! is_string($key) && ! $key instanceof Stringable) {
            return null;
        }

        $key = (string) $key;

        return $key === '' ? null : $key;
    }

    /**
     * SHA-256 of the reactor and reactable, independent of the reaction type.
     *
     * @param  array{0: string, 1: string}  $reactor
     * @param  array{0: string, 1: string}  $reactable
     */
    public static function pairHash(array $reactor, array $reactable): string
    {
        return hash('sha256', json_encode([$reactor[0], $reactor[1], $reactable[0], $reactable[1]], self::JSON));
    }

    /**
     * SHA-256 of the reactor, reactable and canonical key.
     *
     * @param  array{0: string, 1: string}  $reactor
     * @param  array{0: string, 1: string}  $reactable
     */
    public static function identityHash(array $reactor, array $reactable, string $key): string
    {
        return hash('sha256', json_encode([$reactor[0], $reactor[1], $reactable[0], $reactable[1], $key], self::JSON));
    }
}
