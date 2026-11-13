<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * What to show for the reactions of one reactable, ready for Blade or JSON.
 * A read model: it never writes, and it holds only scalars and arrays.
 *
 * Shape of toArray():
 *
 *     mode               "single" or "multiple"
 *     free_form_enabled  whether the reactable's group accepts free-form emoji
 *     total              every stored reaction row, including historic types
 *     choices            the group's registered types, in catalog order, with zero counts
 *     used_other         stored types that are not choices: types the group no
 *                        longer offers, and free-form emoji, most used first
 *
 * Each item has kind ("registered" or "emoji"), key, label, emoji, icon,
 * count, selected (the viewer has this reaction; always false without a
 * viewer) and selectable (the group offers it; not an authorization decision).
 *
 * @phpstan-type Item array{kind: string, key: string, label: string, emoji: string|null, icon: string|null, count: int, selected: bool, selectable: bool}
 * @phpstan-type View array{mode: string, free_form_enabled: bool, total: int, choices: list<Item>, used_other: list<Item>}
 */
final class ReactionView implements Arrayable
{
    /**
     * @param  View  $data
     *
     * @internal use ReactionView::for() or ReactionView::forMany()
     */
    public function __construct(private array $data)
    {
    }

    /**
     * The view of one saved reactable, for a viewer (null: nobody is signed in).
     *
     * @throws \InvalidArgumentException when the reactable is unsaved
     */
    public static function for(Model $reactable, ?Model $viewer = null): self
    {
        return new self(self::forMany([$reactable], $viewer)->get($reactable));
    }

    /**
     * The views of many saved reactables (of any classes) in two queries: one
     * for every count, one for the viewer's reactions. Pass a paginator's
     * getCollection(), not the paginator.
     *
     * @param  iterable<mixed>  $reactables
     *
     * @throws \InvalidArgumentException when a reactable is unsaved, or is not a model
     */
    public static function forMany(iterable $reactables, ?Model $viewer = null): ReactionViewBatch
    {
        return ReactionViewBatch::build($reactables, $viewer);
    }

    /**
     * @return View
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
