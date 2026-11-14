<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pharaonic\Laravel\Reacts\Support\ReactionManager;
use Pharaonic\Laravel\Reacts\Support\ReactionModelResolver;

/**
 * One reaction of a reactor model on a reactable model.
 *
 * The package's own reads ignore global scopes of a custom model; the
 * reactions() and givenReactions() relations are plain Eloquent and apply them.
 *
 * @property int $id
 * @property string $reactor_type
 * @property string $reactor_id
 * @property string $reactable_type
 * @property string $reactable_id
 * @property string $type_key the registered key, or the canonical emoji
 * @property string $identity_hash
 * @property string $pair_hash
 * @property string|null $single_slot_hash the pair hash while the row is a single-mode reaction
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Reaction extends Model
{
    /**
     * The table from pharaonic.reacts.table, unless a subclass sets $table.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table ?: ReactionModelResolver::table();
    }

    /**
     * The connection from pharaonic.reacts.connection, unless a subclass sets $connection.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        return $this->connection ?? ReactionModelResolver::connection();
    }

    /**
     * The model that reacted.
     *
     * @return MorphTo<Model, $this>
     */
    public function reactor(): MorphTo
    {
        return $this->morphTo('reactor', 'reactor_type', 'reactor_id');
    }

    /**
     * The model that was reacted to.
     *
     * @return MorphTo<Model, $this>
     */
    public function reactable(): MorphTo
    {
        return $this->morphTo('reactable', 'reactable_type', 'reactable_id');
    }

    /**
     * Only the reactions the reactor gave.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereReactor(Builder $query, Model $reactor): void
    {
        ReactionManager::constrain($query, 'reactor', $reactor);
    }

    /**
     * Only the reactions the reactable received.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereReactable(Builder $query, Model $reactable): void
    {
        ReactionManager::constrain($query, 'reactable', $reactable);
    }

    /**
     * Only the reactions of one type ("love", or an emoji such as "🚀").
     *
     * @param  Builder<static>  $query
     *
     * @throws \Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType when the type is invalid
     */
    public function scopeWhereType(Builder $query, string $type): void
    {
        ReactionManager::constrainType($query, $type);
    }
}
