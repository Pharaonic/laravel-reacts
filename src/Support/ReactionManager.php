<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Support;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Reacts\Catalog\CatalogResolver;
use Pharaonic\Laravel\Reacts\Contracts\ReactionPolicy;
use Pharaonic\Laravel\Reacts\Events\ReactionCreated;
use Pharaonic\Laravel\Reacts\Events\ReactionRemoved;
use Pharaonic\Laravel\Reacts\Events\ReactionReplaced;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Exceptions\ReactionNotAllowed;
use Pharaonic\Laravel\Reacts\Models\Reaction;
use Pharaonic\Laravel\Reacts\Relations\ReactionsMorphMany;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionContext;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;
use RuntimeException;

/**
 * The one place that reads and writes reactions, shared by CanReact and HasReactions.
 *
 * The reactions table enforces the rules itself: identity_hash is unique per
 * reactor, reactable and type, and single_slot_hash (set only on
 * single-mode rows) is unique per reactor and reactable. A write runs in a
 * transaction, locks the pair's rows, and when a concurrent request inserts
 * first, reads the pair again and decides anew. Outside a caller's
 * transaction Laravel retries a deadlock or a locked SQLite file up to
 * ATTEMPTS times; inside one, the error goes to the caller. Events are
 * dispatched right after the write's own transaction commits.
 *
 * @internal
 */
final class ReactionManager
{
    private const ATTEMPTS = 5;

    /**
     * Add the reaction; in single mode it replaces the pair's other reaction.
     * True when the stored state changed.
     */
    public static function react(Model $reactor, Model $reactable, string $type): bool
    {
        return self::write($reactor, $reactable, $type, false);
    }

    /**
     * Remove the reaction when it exists, or add it. The new state of the type.
     */
    public static function toggle(Model $reactor, Model $reactable, string $type): bool
    {
        return self::write($reactor, $reactable, $type, true);
    }

    /**
     * Remove one reaction type, even one the catalog no longer offers. True when a row was removed.
     */
    public static function unreact(Model $reactor, Model $reactable, string $type): bool
    {
        $key = ReactionIdentity::normalize($type);
        $ends = self::ends($reactor, $reactable);

        $query = self::query()->where('identity_hash', ReactionIdentity::identityHash($ends[0], $ends[1], $key));
        $query->getQuery()->useWritePdo();
        $reaction = $query->first();

        if (! $reaction instanceof Reaction || ! self::deleteOne($reaction)) {
            return false;
        }

        Event::dispatch(new ReactionRemoved($reaction, $reactor, $reactable));

        return true;
    }

    /**
     * Remove every reaction of the reactor on the reactable. The number removed.
     */
    public static function clear(Model $reactor, Model $reactable): int
    {
        $ends = self::ends($reactor, $reactable);
        $pair = ReactionIdentity::pairHash($ends[0], $ends[1]);
        $connection = ReactionModelResolver::reaction()->getConnection();

        /** @var list<Reaction> $removed */
        $removed = $connection->transaction(function () use ($pair) {
            self::lockForWriting();

            return self::delete(self::rows($pair));
        }, self::ATTEMPTS);

        foreach ($removed as $reaction) {
            Event::dispatch(new ReactionRemoved($reaction, $reactor, $reactable));
        }

        return count($removed);
    }

    /**
     * Whether the reactor reacted to the reactable, with any type or with this one.
     */
    public static function has(Model $reactor, Model $reactable, ?string $type = null): bool
    {
        $key = $type === null ? null : ReactionIdentity::normalize($type);

        if (! ReactionIdentity::isPersisted($reactor) || ! ReactionIdentity::isPersisted($reactable)) {
            return false;
        }

        $ends = self::ends($reactor, $reactable);

        if ($key === null) {
            return self::query()->where('pair_hash', ReactionIdentity::pairHash($ends[0], $ends[1]))->exists();
        }

        return self::query()->where('identity_hash', ReactionIdentity::identityHash($ends[0], $ends[1], $key))->exists();
    }

    /**
     * The reactor's reactions on the reactable, oldest first. Empty for an unsaved model.
     */
    public static function on(Model $reactor, Model $reactable): Collection
    {
        $reaction = ReactionModelResolver::reaction();

        if (! ReactionIdentity::isPersisted($reactor) || ! ReactionIdentity::isPersisted($reactable)) {
            return $reaction->newCollection();
        }

        $ends = self::ends($reactor, $reactable);
        $query = self::query($reaction)->where('pair_hash', ReactionIdentity::pairHash($ends[0], $ends[1]));
        $query->getQuery()->orderBy($reaction->getKeyName());

        return $query->get();
    }

    /**
     * Stored reactions of a reactable per key, most first, then by key.
     *
     * @return array<string, int>
     */
    public static function counts(Model $reactable): array
    {
        $counts = [];

        if ($reactable->relationLoaded('reactions')) {
            $loaded = $reactable->getRelation('reactions');

            foreach ($loaded instanceof Collection ? self::models($loaded) : [] as $reaction) {
                $counts[$reaction->type_key] = ($counts[$reaction->type_key] ?? 0) + 1;
            }

            return self::sortCounts($counts);
        }

        if (! ReactionIdentity::isPersisted($reactable)) {
            return [];
        }

        [$type, $key] = ReactionIdentity::endpoint($reactable, 'reactable');

        $rows = self::query()->toBase()
            ->where('reactable_type', $type)
            ->where('reactable_id', $key)
            ->groupBy('type_key')
            ->select(['type_key'])
            ->selectRaw('count(*) as aggregate')
            ->get();

        foreach ($rows as $row) {
            $row = (array) $row;
            $typeKey = $row['type_key'] ?? '';
            $aggregate = $row['aggregate'] ?? 0;
            $counts[is_scalar($typeKey) ? (string) $typeKey : ''] = is_numeric($aggregate) ? (int) $aggregate : 0;
        }

        return self::sortCounts($counts);
    }

    /**
     * The reactions given (reactor) or received (reactable) by the model.
     */
    public static function relation(Model $parent, string $side): MorphMany
    {
        $reaction = ReactionModelResolver::reaction();
        $table = $reaction->getTable();

        return new ReactionsMorphMany(
            $reaction->newQuery(),
            $parent,
            $table.'.'.$side.'_type',
            $table.'.'.$side.'_id',
            $parent->getKeyName()
        );
    }

    /**
     * Limit a reactions query to one reactor or reactable. An unsaved model matches nothing.
     */
    public static function constrain(Builder $query, string $side, Model $model): void
    {
        if (! ReactionIdentity::isPersisted($model)) {
            $query->whereRaw('0 = 1');

            return;
        }

        [$type, $key] = ReactionIdentity::endpoint($model, $side);

        $query->where($query->qualifyColumn($side.'_type'), $type);
        $query->where($query->qualifyColumn($side.'_id'), $key);
    }

    /**
     * Limit a reactions query to one reaction type.
     */
    public static function constrainType(Builder $query, string $type): void
    {
        $query->where($query->qualifyColumn('type_key'), ReactionIdentity::normalize($type));
    }

    /**
     * Delete the reactions of a model that is being deleted, unless it is only
     * soft deleted and soft deletes keep them. No policy, no events.
     */
    public static function cleanUp(Model $model, string $side): void
    {
        if (! Settings::cleanupOnDelete()) {
            return;
        }

        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting() && Settings::preserveOnSoftDelete()) {
            return;
        }

        $key = ReactionIdentity::key($model);

        if ($key === null) {
            return;
        }

        self::query()->toBase()
            ->where($side.'_type', $model->getMorphClass())
            ->where($side.'_id', $key)
            ->delete();
    }

    /**
     * React, or toggle, in one transaction.
     */
    private static function write(Model $reactor, Model $reactable, string $type, bool $toggle): bool
    {
        $key = ReactionIdentity::normalize($type);
        $ends = self::ends($reactor, $reactable);
        $identity = ReactionIdentity::identityHash($ends[0], $ends[1], $key);
        $pair = ReactionIdentity::pairHash($ends[0], $ends[1]);
        $catalog = CatalogResolver::forModel($reactable);
        $mode = CatalogResolver::modeOf($reactable);
        $single = $mode === Settings::MODE_SINGLE;

        // react() always needs a selectable type; toggleReaction() only when it
        // adds one, so a type the catalog no longer offers can be toggled off.
        $definition = $toggle ? null : CatalogResolver::definitionFor($catalog, $key);

        $connection = ReactionModelResolver::reaction()->getConnection();

        /** @var array{0: bool, 1: bool, 2: object|null} $result the type's state, whether anything changed, the event */
        $result = $connection->transaction(function () use ($reactor, $reactable, $ends, $key, $identity, $pair, $mode, $single, $catalog, $toggle, $definition) {
            $authorized = false;
            self::lockForWriting();

            // On an empty pair, a concurrent request may insert between this read and
            // this insert: the unique index refuses the insert, and the next round sees its row.
            for ($round = 1; $round <= self::ATTEMPTS; $round++) {
                $exact = null;
                $others = [];
                $slotted = false;

                foreach (self::rows($pair, $round === 1) as $row) {
                    $slotted = $slotted || $row->single_slot_hash !== null;

                    if ($row->identity_hash === $identity) {
                        $exact = $row;
                    } else {
                        $others[] = $row;
                    }
                }

                if ($exact !== null && $toggle) {
                    return self::deleteOne($exact)
                        ? [false, true, new ReactionRemoved($exact, $reactor, $reactable)]
                        : [false, false, null];
                }

                if ($exact !== null) {
                    if (! $single) {
                        // Back in multiple mode: free the single-mode slot. Not a visible change.
                        if ($slotted) {
                            self::clearSlots($pair);
                        }

                        return [true, false, null];
                    }

                    // Single mode over rows left from multiple mode: keep this one alone.
                    $previous = self::delete($others);
                    self::claimSlot($exact, $pair);

                    return $previous === []
                        ? [true, false, null]
                        : [true, true, new ReactionReplaced($exact, $previous, $reactor, $reactable)];
                }

                $definition = $definition ?? CatalogResolver::definitionFor($catalog, $key);

                if (! $authorized) {
                    self::authorize($reactor, $reactable, $definition, new ReactionContext($catalog->group, $mode));
                    $authorized = true;
                }

                if ($single && $others !== []) {
                    // Replace in place: the newest row, still locked, becomes the new type,
                    // so concurrent writers on the pair wait for it instead of racing for the slot.
                    $kept = array_pop($others);
                    $replaced = clone $kept;
                    $replaced->exists = false;
                    $previous = array_merge(self::delete($others), [$replaced]);

                    return [true, true, new ReactionReplaced(self::retype($kept, $key, $identity, $pair), $previous, $reactor, $reactable)];
                }

                if (! $single && $slotted) {
                    self::clearSlots($pair);
                }

                $current = self::insert($ends, $key, $identity, $pair, $single ? $pair : null);

                if ($current !== null) {
                    return [true, true, new ReactionCreated($current, $reactor, $reactable)];
                }
            }

            throw new RuntimeException(sprintf(
                'The reactions of this pair kept changing while they were written, %d times in a row. Try again.', self::ATTEMPTS
            ));
        }, self::ATTEMPTS);

        [$state, $changed, $event] = $result;

        if ($event !== null) {
            Event::dispatch($event);
        }

        return $toggle ? $state : $changed;
    }

    /**
     * On SQLite, take the database write lock before reading: a transaction
     * that reads first cannot wait for another writer when it then writes,
     * and fails at once with "database is locked". A write that changes
     * nothing takes the lock, waiting for the busy timeout like any write.
     * Other databases lock the pair's rows in rows().
     */
    private static function lockForWriting(): void
    {
        $reaction = ReactionModelResolver::reaction();
        $connection = $reaction->getConnection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        self::query($reaction)->toBase()->whereRaw('1 = 0')->update(['pair_hash' => '']);
    }

    /**
     * The pair's reactions, oldest first, read and locked from the write connection.
     *
     * @return list<Reaction>
     */
    private static function rows(string $pair, bool $firstRound = false): array
    {
        $reaction = ReactionModelResolver::reaction();

        // On MySQL a locking read of a pair with no rows takes a gap lock, and
        // concurrent writers that each hold one deadlock on their inserts. So the
        // first read is a plain one: an empty pair goes straight to the insert,
        // which the unique indexes guard; a pair with rows is read again, locked.
        if ($firstRound && in_array($reaction->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $plain = self::query($reaction)->where('pair_hash', $pair);
            $plain->getQuery()->useWritePdo();

            if (! $plain->exists()) {
                return [];
            }
        }

        $query = self::query($reaction)->where('pair_hash', $pair);
        $query->getQuery()->orderBy($reaction->getKeyName())->useWritePdo()->lockForUpdate();

        return self::models($query->get());
    }

    /**
     * Insert the reaction, or null when a unique index refused it because a
     * concurrent request stored the same reaction or took the pair's single slot.
     *
     * @param  array{0: array{0: string, 1: string}, 1: array{0: string, 1: string}}  $ends
     */
    private static function insert(array $ends, string $key, string $identity, string $pair, ?string $slot): ?Reaction
    {
        $reaction = ReactionModelResolver::reaction();
        $reaction->forceFill([
            'reactor_type' => $ends[0][0],
            'reactor_id' => $ends[0][1],
            'reactable_type' => $ends[1][0],
            'reactable_id' => $ends[1][1],
            'type_key' => $key,
            'identity_hash' => $identity,
            'pair_hash' => $pair,
            'single_slot_hash' => $slot,
        ]);

        try {
            // A savepoint keeps a refused insert from aborting the transaction on PostgreSQL.
            $saved = $reaction->getConnection()->transaction(function () use ($reaction) {
                return $reaction->save();
            });
        } catch (QueryException $exception) {
            if (self::isUniqueViolation($exception)) {
                return null;
            }

            throw $exception;
        }

        if (! $saved) {
            throw new RuntimeException('A model event cancelled saving the reaction, so it was not created.');
        }

        return $reaction;
    }

    /**
     * Whether the database refused a row because of a unique index.
     */
    private static function isUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [];
        $sqlState = isset($errorInfo[0]) && is_string($errorInfo[0]) ? $errorInfo[0] : (string) $exception->getCode();

        // PostgreSQL: unique_violation. MySQL and MariaDB: ER_DUP_ENTRY.
        if ($sqlState === '23505' || in_array($errorInfo[1] ?? null, [1062, 1586], true)) {
            return true;
        }

        // SQLite reports every constraint failure as code 19.
        return str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * Delete the rows with a query-builder delete (no Eloquent model events).
     *
     * @param  list<Reaction>  $rows
     * @return list<Reaction> the rows deleted
     */
    private static function delete(array $rows): array
    {
        $deleted = [];

        foreach ($rows as $row) {
            if (self::deleteOne($row)) {
                $deleted[] = $row;
            }
        }

        return $deleted;
    }

    /**
     * Delete one row. True when this call deleted it.
     */
    private static function deleteOne(Reaction $reaction): bool
    {
        $deleted = self::query()->toBase()->where($reaction->getKeyName(), $reaction->getKey())->delete() > 0;

        if ($deleted) {
            $reaction->exists = false;
        }

        return $deleted;
    }

    /**
     * Turn a locked single-mode row into another type, with a query-builder
     * update (no Eloquent model events). It keeps its id and is new from now.
     */
    private static function retype(Reaction $reaction, string $key, string $identity, string $pair): Reaction
    {
        $now = $reaction->freshTimestampString();
        $changes = ['type_key' => $key, 'identity_hash' => $identity, 'single_slot_hash' => $pair];

        if ($reaction->usesTimestamps()) {
            $changes += [$reaction->getCreatedAtColumn() => $now, $reaction->getUpdatedAtColumn() => $now];
        }

        self::query()->toBase()->where($reaction->getKeyName(), $reaction->getKey())->update($changes);
        $reaction->setRawAttributes($changes + $reaction->getAttributes(), true);

        return $reaction;
    }

    private static function claimSlot(Reaction $reaction, string $pair): void
    {
        if ($reaction->single_slot_hash === $pair) {
            return;
        }

        self::query()->toBase()->where($reaction->getKeyName(), $reaction->getKey())->update(['single_slot_hash' => $pair]);
        $reaction->setRawAttributes(['single_slot_hash' => $pair] + $reaction->getAttributes(), true);
    }

    private static function clearSlots(string $pair): void
    {
        self::query()->toBase()->where('pair_hash', $pair)->whereNotNull('single_slot_hash')->update(['single_slot_hash' => null]);
    }

    /**
     * @throws ReactionNotAllowed when the policy refuses the reaction
     */
    private static function authorize(Model $reactor, Model $reactable, ReactionDefinition $definition, ReactionContext $context): void
    {
        $class = Settings::get('policy', DefaultReactionPolicy::class);

        if (! is_string($class) || ! is_a($class, ReactionPolicy::class, true)) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.policy setting must name a class implementing %s, not %s.',
                ReactionPolicy::class, Settings::describe($class)
            ));
        }

        $policy = Container::getInstance()->make($class);

        if (! $policy instanceof ReactionPolicy || ! $policy->allows($reactor, $reactable, $definition, $context)) {
            throw new ReactionNotAllowed(sprintf(
                'The reaction policy does not allow [%s] to react with [%s] to [%s].',
                get_class($reactor), $definition->key(), get_class($reactable)
            ));
        }
    }

    /**
     * @return array{0: array{0: string, 1: string}, 1: array{0: string, 1: string}}
     */
    private static function ends(Model $reactor, Model $reactable): array
    {
        return [ReactionIdentity::endpoint($reactor, 'reactor'), ReactionIdentity::endpoint($reactable, 'reactable')];
    }

    /**
     * A query on the reactions table without the model's global scopes.
     */
    private static function query(?Reaction $reaction = null): Builder
    {
        $reaction = $reaction ?? ReactionModelResolver::reaction();

        return (new Builder($reaction->getConnection()->query()))->setModel($reaction);
    }

    /**
     * @return list<Reaction>
     */
    private static function models(Collection $rows): array
    {
        $models = [];

        foreach ($rows as $row) {
            if ($row instanceof Reaction) {
                $models[] = $row;
            }
        }

        return $models;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private static function sortCounts(array $counts): array
    {
        uksort($counts, function ($a, $b) use ($counts) {
            return [$counts[$b], (string) $a] <=> [$counts[$a], (string) $b];
        });

        return $counts;
    }
}
