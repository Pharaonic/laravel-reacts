<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Reacts\Support\ReactionManager;

/**
 * For a model that other models react to, such as a post.
 */
trait HasReactions
{
    /**
     * Delete the reactions the model received when it is deleted (see pharaonic.reacts.cleanup_on_delete).
     */
    public static function bootHasReactions(): void
    {
        static::deleted(function (Model $model) {
            ReactionManager::cleanUp($model, 'reactable');
        });
    }

    /**
     * The reactions this model received.
     */
    public function reactions(): MorphMany
    {
        return ReactionManager::relation($this, 'reactable');
    }

    /**
     * React to this model as the reactor, the same as $reactor->react($this, $type).
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is invalid or not selectable
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed when the policy refuses it
     * @throws \InvalidArgumentException when either model is unsaved
     */
    public function reactBy(Model $reactor, string $type): bool
    {
        return ReactionManager::react($reactor, $this, $type);
    }

    /**
     * Remove the reactor's reaction of this type. True when a row was removed.
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is not a valid key or emoji
     * @throws \InvalidArgumentException when either model is unsaved
     */
    public function unreactBy(Model $reactor, string $type): bool
    {
        return ReactionManager::unreact($reactor, $this, $type);
    }

    /**
     * Whether the reactor reacted to this model, with any type or with this one.
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is not a valid key or emoji
     */
    public function reactedBy(Model $reactor, ?string $type = null): bool
    {
        return ReactionManager::has($reactor, $this, $type);
    }

    /**
     * The stored reactions per key (registered key or emoji), most first, then
     * by key. Counts every stored row, including types the catalog no longer
     * offers. Uses the loaded reactions relation when there is one.
     *
     * @return array<string, int>
     */
    public function reactionCounts(): array
    {
        return ReactionManager::counts($this);
    }

    /**
     * The key with the most reactions (ties: the smallest key), or null.
     */
    public function topReaction(): ?string
    {
        $key = array_key_first($this->reactionCounts());

        return $key === null ? null : (string) $key;
    }

    /**
     * Only the models the reactor reacted to, with any type or with this one.
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is not a valid key or emoji
     */
    public function scopeWhereReactedBy(Builder $query, Model $reactor, ?string $type = null): void
    {
        $query->whereHas('reactions', function (Builder $reactions) use ($reactor, $type) {
            ReactionManager::constrain($reactions, 'reactor', $reactor);

            if ($type !== null) {
                ReactionManager::constrainType($reactions, $type);
            }
        });
    }
}
