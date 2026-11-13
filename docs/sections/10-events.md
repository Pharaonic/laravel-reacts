## Events

The package dispatches an event for every change it makes, and none when nothing changed. Each one is dispatched right after the write's own transaction commits.

| Event | When | Properties |
| --- | --- | --- |
| `ReactionCreated` | A reaction was added without replacing another | `$reaction`, `$reactor`, `$reactable` |
| `ReactionReplaced` | In single mode, a new type replaced the previous one | `$current`, `$previous` (the replaced reactions as they were), `$reactor`, `$reactable` |
| `ReactionRemoved` | `unreact()`, `toggleReaction()` or `clearReactions()` removed a reaction | `$reaction` (with `exists` false), `$reactor`, `$reactable` |

All three live in `Pharaonic\Laravel\Reacts\Events`.

```php title="app/Listeners/NotifyAuthor.php"
namespace App\Listeners;

use Pharaonic\Laravel\Reacts\Events\ReactionCreated;

class NotifyAuthor
{
    public function handle(ReactionCreated $event): void
    {
        $event->reactable->author->notify(new \App\Notifications\NewReaction($event->reactor, $event->reaction->type_key));
    }
}
```

`$previous` usually holds one reaction: the row before the change, with `exists` false. The replacement updates that row in place, so `$current` has the same id. `$previous` holds several reactions when single mode replaces rows left from multiple mode; the others were deleted.

No event is dispatched when `react()` finds the reaction already there, when `unreact()` has nothing to remove, or when a model's deletion removes its reactions.

:::info Inside your own transaction
When you call the package inside `DB::transaction()`, the events are dispatched before your transaction commits, and a rollback does not take them back. When that matters, do the follow-up work after your transaction returns.
:::
