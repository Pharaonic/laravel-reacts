<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Support\ReactionManager;

/**
 * For a model that reacts to other models, such as a user.
 */
trait CanReact
{
    /**
     * Delete the reactions the model gave when it is deleted (see pharaonic.reacts.cleanup_on_delete).
     */
    public static function bootCanReact(): void
    {
        static::deleted(function (Model $model) {
            ReactionManager::cleanUp($model, 'reactor');
        });
    }

    /**
     * The reactions this model gave.
     */
    public function givenReactions(): MorphMany
    {
        return ReactionManager::relation($this, 'reactor');
    }

    /**
     * React with a type: a registered key ("love") or, where the group allows
     * it, one emoji ("🚀"). True when the stored state changed: in multiple
     * mode a new type was added; in single mode it was added or replaced the
     * previous one. False when the reaction already exists.
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is invalid or not selectable
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed when the policy refuses it
     * @throws \InvalidArgumentException when either model is unsaved
     */
    public function react(Model $reactable, string $type): bool
    {
        return ReactionManager::react($this, $reactable, $type);
    }

    /**
     * Remove one reaction type, even one the catalog no longer offers. True when a row was removed.
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is not a valid key or emoji
     * @throws \InvalidArgumentException when either model is unsaved
     */
    public function unreact(Model $reactable, string $type): bool
    {
        return ReactionManager::unreact($this, $reactable, $type);
    }

    /**
     * Remove the reaction type when it exists, or add it (replacing the
     * previous type in single mode). Returns the type's new state.
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when adding a type that is invalid or not selectable
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed when the policy refuses it
     * @throws \InvalidArgumentException when either model is unsaved
     */
    public function toggleReaction(Model $reactable, string $type): bool
    {
        return ReactionManager::toggle($this, $reactable, $type);
    }

    /**
     * Remove every reaction this model gave the reactable. The number removed.
     *
     * @throws \InvalidArgumentException when either model is unsaved
     */
    public function clearReactions(Model $reactable): int
    {
        return ReactionManager::clear($this, $reactable);
    }

    /**
     * Whether this model reacted to the reactable, with any type or with this one.
     * False when either model is unsaved.
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is not a valid key or emoji
     */
    public function hasReacted(Model $reactable, ?string $type = null): bool
    {
        return ReactionManager::has($this, $reactable, $type);
    }

    /**
     * This model's reactions on the reactable, oldest first.
     *
     * @return Collection<int, Reaction>
     */
    public function reactionsOn(Model $reactable): Collection
    {
        return ReactionManager::on($this, $reactable);
    }
}
