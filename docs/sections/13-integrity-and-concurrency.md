## Integrity and Concurrency

The rules live in the `reactions` table, not only in PHP, so two requests at the same moment cannot break them.

| Column | Unique | Keeps |
| --- | --- | --- |
| `identity_hash` | Yes | One row per reactor, reactable and type. |
| `single_slot_hash` | Yes, when set | One row per reactor and reactable in single mode. It is `NULL` on multiple-mode rows. |
| `pair_hash` | No (indexed) | Finds a reactor's reactions on a reactable. |

The hashes are SHA-256 of the morph types, keys and type, so the indexes stay small on every database. A collision is theoretically possible but practically never happens.

### How a write runs

`react()` and `toggleReaction()` run in one transaction and lock the pair's existing rows first, so writes on the same pair wait for each other. In single mode a replacement updates the locked row in place rather than deleting and inserting, so the pair is never empty in between. Only on a pair with no reaction yet can two requests insert at once: the unique index refuses the second insert, and that write reads the pair again and decides anew, so a duplicate becomes a no-op and a different type in single mode becomes a replacement. Outside your own transaction, Laravel retries a deadlock or a busy SQLite database up to five times.

| Situation | Result |
| --- | --- |
| Eight requests react `like` at once | One row, one `true`, one `ReactionCreated` |
| Eight different types at once, multiple mode | One row per type |
| Eight different types at once, single mode | One row, one `ReactionCreated`, then a `ReactionReplaced` for each request that changed the type |

### Reconciling after a mode change

Switching a reactable to single mode leaves pairs with several rows until their next write. To reconcile everything at once, keep the newest row of each pair and mark it as the single-mode row, for example for posts:

```php
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Reacts\Models\Reaction;

$type = (new Post)->getMorphClass();

DB::transaction(function () use ($type) {
    $newest = Reaction::query()->where('reactable_type', $type)
        ->groupBy('pair_hash')->selectRaw('max(id) as id')->pluck('id');

    Reaction::query()->where('reactable_type', $type)->whereNotIn('id', $newest)->delete();
    Reaction::query()->whereIn('id', $newest)->update(['single_slot_hash' => DB::raw('pair_hash')]);
});
```

After a switch back to multiple mode nothing needs to change; to tidy up, set `single_slot_hash` to `NULL` for that reactable type.

:::warning Rows written around the package
Rows you insert yourself must carry the same hashes the package writes. Use `react()` for imports when you can.
:::
