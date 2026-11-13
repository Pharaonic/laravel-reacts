## Relationships and Queries

### Relations

`reactions()` (from `HasReactions`) and `givenReactions()` (from `CanReact`) are plain `MorphMany` relations, so eager loading and counting work as usual:

```php
$posts = Post::query()->withCount('reactions')->with('reactions.reactor')->latest()->get();

$posts[0]->reactions_count;          // 15
$posts[0]->reactions[0]->reactor;    // the User who reacted

$user->givenReactions()->with('reactable')->latest()->get();
```

Each `Reaction` has `reactor()` and `reactable()` `MorphTo` relations, and the `type_key` attribute: the registered key or the emoji.

### Filtering reactables

`whereReactedBy()` keeps the reactables a reactor reacted to, with any type or with one:

```php
Post::whereReactedBy($user)->latest()->paginate();
Post::whereReactedBy($user, 'love')->count();
```

### Filtering reactions

The `Reaction` model has three scopes:

```php
use Pharaonic\Laravel\Reacts\Models\Reaction;

Reaction::query()->whereReactor($user)->count();
Reaction::query()->whereReactable($post)->whereType('love')->count();
Reaction::query()->whereType('🚀')->with('reactable')->get();
```

An unsaved model matches nothing.

### Counts on many reactables

For one reactable, `reactionCounts()` runs one grouped query. For a list, use [ReactionView::forMany()](#listing-reactions), or a grouped query of your own:

```php
Reaction::query()
    ->whereReactable($post)
    ->groupBy('type_key')
    ->selectRaw('type_key, count(*) as total')
    ->pluck('total', 'type_key');
```

### Morph maps and ids

The table stores the morph type from `getMorphClass()`, so aliases from `Relation::morphMap()` are used, and keys as strings of up to 64 characters, so integer, UUID and ULID models share the table.

:::warning Changing morph aliases or keys
Stored rows keep the morph type and key they were written with. Changing a morph alias, or a model's key type, needs a data migration, as with any Laravel polymorphic relation.
:::

:::info Global scopes
The package's own reads and writes ignore global scopes on a custom reaction model. The `reactions()` and `givenReactions()` relations are plain Eloquent and apply them.
:::
