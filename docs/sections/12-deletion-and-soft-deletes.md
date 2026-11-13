## Deletes and Soft Deletes

When a model with `CanReact` is deleted, the reactions it gave are deleted. When a model with `HasReactions` is deleted, the reactions it received are deleted. A model with both traits loses both.

| Setting | Default | Effect |
| --- | --- | --- |
| `cleanup_on_delete` | `true` | `false` keeps reactions when a model is deleted. |
| `preserve_on_soft_delete` | `true` | A soft delete keeps the reactions, so a restored model has them back. `false` deletes them on a soft delete too. |

A force delete always removes them while `cleanup_on_delete` is on. Cleanup runs without the reaction policy and dispatches no `ReactionRemoved`.

### Mass deletes

Query deletes such as `Post::where('draft', true)->delete()` do not fire model events, so they leave the reactions behind. Delete them with the same query on the reactions table:

```php
use Pharaonic\Laravel\Reacts\Models\Reaction;

$ids = Post::where('draft', true)->pluck('id');

Reaction::query()
    ->where('reactable_type', (new Post)->getMorphClass())
    ->whereIn('reactable_id', $ids->map(function ($id) {
        return (string) $id;
    }))
    ->delete();

Post::whereKey($ids)->delete();
```
