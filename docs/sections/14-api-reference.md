## API Reference

### CanReact

For the model that reacts.

| Method | Description | Returns |
| --- | --- | --- |
| `react(Model $reactable, string $type)` | Add a reaction; in single mode it replaces the previous one. | `bool`: the state changed |
| `unreact(Model $reactable, string $type)` | Remove one type, also one no group offers any more. | `bool`: a row was removed |
| `toggleReaction(Model $reactable, string $type)` | Remove the type when it exists, add it otherwise. | `bool`: the type's new state |
| `clearReactions(Model $reactable)` | Remove every reaction on the reactable. | `int`: rows removed |
| `hasReacted(Model $reactable, ?string $type = null)` | Any reaction, or this type. `false` for unsaved models. | `bool` |
| `reactionsOn(Model $reactable)` | This model's reactions on the reactable, oldest first. | `Collection` of `Reaction` |
| `givenReactions()` | The reactions this model gave. | `MorphMany` |

### HasReactions

For the model that receives reactions.

| Method | Description | Returns |
| --- | --- | --- |
| `reactBy(Model $reactor, string $type)` | The same as `$reactor->react($this, $type)`. | `bool` |
| `unreactBy(Model $reactor, string $type)` | The same as `$reactor->unreact($this, $type)`. | `bool` |
| `reactedBy(Model $reactor, ?string $type = null)` | The same as `$reactor->hasReacted($this, $type)`. | `bool` |
| `reactionCounts()` | Stored reactions per key, most first, then by key. | `array<string, int>` |
| `topReaction()` | The key with the most reactions; ties go to the smaller key. | `?string` |
| `reactions()` | The reactions this model received. | `MorphMany` |
| `scopeWhereReactedBy(Builder $query, Model $reactor, ?string $type = null)` | `Post::whereReactedBy($user, 'love')` | `void` |

### Reaction model

`Pharaonic\Laravel\Reacts\Models\Reaction`

| Member | Description |
| --- | --- |
| `reactor()`, `reactable()` | `MorphTo` relations. |
| `scopeWhereReactor(Model)`, `scopeWhereReactable(Model)`, `scopeWhereType(string)` | Query scopes. |
| `reactor_type`, `reactor_id`, `reactable_type`, `reactable_id` | The two models, ids as strings. |
| `type_key` | The registered key, or the canonical emoji. |
| `identity_hash`, `pair_hash`, `single_slot_hash` | See [Integrity and Concurrency](#integrity-and-concurrency). |

### ReactionView

`Pharaonic\Laravel\Reacts\Support\ReactionView` and `ReactionViewBatch`

| Method | Returns |
| --- | --- |
| `ReactionView::for(Model $reactable, ?Model $viewer = null)` | `ReactionView` |
| `ReactionView::forMany(iterable $reactables, ?Model $viewer = null)` | `ReactionViewBatch` |
| `ReactionView::toArray()` | The array described in [Listing Reactions](#listing-reactions) |
| `ReactionViewBatch::get(Model $reactable)` | The same array for one model of the batch |
| `ReactionViewBatch::has(Model $reactable)` | `bool` |

### Contracts

| Interface | Method |
| --- | --- |
| `ResolvesReactionGroup` | `reactionGroup(): string`, a group in `groups` |
| `ResolvesReactionMode` | `reactionMode(): string`, `single` or `multiple` |
| `ReactionPolicy` | `allows(Model $reactor, Model $reactable, ReactionDefinition $definition, ReactionContext $context): bool` |

### Exceptions

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `InvalidReactionType` | `InvalidArgumentException` | The type is not a valid key or one emoji, the group does not offer it, or the group accepts no free-form emoji. |
| `ReactionNotAllowed` | `RuntimeException` | The reaction policy refused a valid type. |
| `InvalidReactionConfiguration` | `UnexpectedValueException` | A setting, `reactionGroup()` or `reactionMode()` is invalid. |
| `InvalidArgumentException` | | A write got an unsaved model. |

All package exceptions live in `Pharaonic\Laravel\Reacts\Exceptions`.
