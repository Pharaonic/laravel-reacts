<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Pharaonic\Laravel\Reacts\Catalog\CatalogResolver;
use Pharaonic\Laravel\Reacts\Catalog\ResolvedCatalog;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;

/**
 * The reaction views of a list of reactables, looked up by model.
 *
 * @phpstan-import-type Item from ReactionView
 * @phpstan-import-type View from ReactionView
 */
final class ReactionViewBatch
{
    /**
     * @param  array<string, View>  $views  by morph type and key
     */
    private function __construct(private array $views)
    {
    }

    /**
     * The view of a reactable in the batch, found by morph type and key.
     *
     * @return View
     *
     * @throws InvalidArgumentException when the reactable was not in the batch
     */
    public function get(Model $reactable): array
    {
        $id = self::idOf($reactable);

        if ($id === null || ! isset($this->views[$id])) {
            throw new InvalidArgumentException(sprintf('The reactable [%s] was not in this ReactionView::forMany() batch.', get_class($reactable)));
        }

        return $this->views[$id];
    }

    /**
     * Whether the reactable is in the batch.
     */
    public function has(Model $reactable): bool
    {
        $id = self::idOf($reactable);

        return $id !== null && isset($this->views[$id]);
    }

    /**
     * @param  iterable<mixed>  $reactables
     *
     * @internal use ReactionView::forMany()
     */
    public static function build(iterable $reactables, ?Model $viewer): self
    {
        $models = [];
        $keysByType = [];

        foreach ($reactables as $reactable) {
            if (! $reactable instanceof Model) {
                throw new InvalidArgumentException('ReactionView::forMany() takes Eloquent models; pass a paginator\'s getCollection().');
            }

            [$type, $key] = ReactionIdentity::endpoint($reactable, 'reactable');

            if (! isset($models[$type."\0".$key])) {
                $models[$type."\0".$key] = $reactable;
                $keysByType[$type][] = $key;
            }
        }

        if ($models === []) {
            return new self([]);
        }

        $counts = [];

        foreach (self::query($keysByType)->select(['reactable_type', 'reactable_id', 'type_key'])->selectRaw('count(*) as aggregate')
            ->groupBy('reactable_type', 'reactable_id', 'type_key')->get() as $row) {
            [$id, $key, $aggregate] = self::row($row);
            $counts[$id][$key] = $aggregate;
        }

        $selected = [];

        if ($viewer !== null && ReactionIdentity::isPersisted($viewer)) {
            [$type, $key] = ReactionIdentity::endpoint($viewer, 'reactor');

            foreach (self::query($keysByType)->where('reactor_type', $type)->where('reactor_id', $key)
                ->select(['reactable_type', 'reactable_id', 'type_key'])->get() as $row) {
                [$id, $reactionKey] = self::row($row);
                $selected[$id][$reactionKey] = true;
            }
        }

        $views = [];

        foreach ($models as $id => $model) {
            $views[$id] = self::view(CatalogResolver::forModel($model), CatalogResolver::modeOf($model), $counts[$id] ?? [], $selected[$id] ?? []);
        }

        return new self($views);
    }

    /**
     * @param  array<string, int>  $counts  stored rows per key
     * @param  array<string, true>  $selected  the viewer's keys
     * @return View
     */
    private static function view(ResolvedCatalog $catalog, string $mode, array $counts, array $selected): array
    {
        $total = array_sum($counts);
        $choices = [];

        foreach ($catalog->definitions as $key => $definition) {
            $choices[] = self::item($definition, $counts[$key] ?? 0, isset($selected[$key]), true);
            unset($counts[$key]);
        }

        uksort($counts, function ($a, $b) use ($counts) {
            return [$counts[$b], (string) $a] <=> [$counts[$a], (string) $b];
        });

        $other = [];

        foreach ($counts as $key => $count) {
            $key = (string) $key;

            if (ReactionDefinition::kindOf($key) === 'emoji') {
                $other[] = self::item(ReactionDefinition::forEmoji($key), $count, isset($selected[$key]), $catalog->freeForm);

                continue;
            }

            // A type no group offers any more: its configured look, or a label made from its key.
            $definition = CatalogResolver::definition($key) ?? new ReactionDefinition($key, ucfirst(str_replace(['_', '-'], ' ', $key)));
            $other[] = self::item($definition, $count, isset($selected[$key]), false);
        }

        return [
            'mode' => $mode,
            'free_form_enabled' => $catalog->freeForm,
            'total' => $total,
            'choices' => $choices,
            'used_other' => $other,
        ];
    }

    /**
     * @return Item
     */
    private static function item(ReactionDefinition $definition, int $count, bool $selected, bool $selectable): array
    {
        return [
            'kind' => $definition->kind(),
            'key' => $definition->key(),
            'label' => $definition->label(),
            'emoji' => $definition->emoji(),
            'icon' => $definition->icon(),
            'count' => $count,
            'selected' => $selected,
            'selectable' => $selectable,
        ];
    }

    /**
     * A reactions query limited to the reactables, grouped by morph type.
     *
     * @param  array<string, list<string>>  $keysByType
     */
    private static function query(array $keysByType): Builder
    {
        $reaction = ReactionModelResolver::reaction();

        return $reaction->getConnection()->table($reaction->getTable())->where(function (Builder $query) use ($keysByType) {
            foreach ($keysByType as $type => $keys) {
                $query->orWhere(function (Builder $query) use ($type, $keys) {
                    $query->where('reactable_type', $type)->whereIn('reactable_id', $keys);
                });
            }
        });
    }

    /**
     * The reactable id, type key and count of a result row.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private static function row(mixed $row): array
    {
        $row = (array) $row;
        $string = static function (mixed $value): string {
            return is_scalar($value) ? (string) $value : '';
        };

        return [
            $string($row['reactable_type'] ?? '')."\0".$string($row['reactable_id'] ?? ''),
            $string($row['type_key'] ?? ''),
            is_numeric($row['aggregate'] ?? null) ? (int) $row['aggregate'] : 0,
        ];
    }

    private static function idOf(Model $reactable): ?string
    {
        if (! ReactionIdentity::isPersisted($reactable)) {
            return null;
        }

        [$type, $key] = ReactionIdentity::endpoint($reactable, 'reactable');

        return $type."\0".$key;
    }
}
