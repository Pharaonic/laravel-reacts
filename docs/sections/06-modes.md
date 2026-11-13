## Single and Multiple Mode

The mode decides how many reactions one reactor keeps on one reactable.

| Mode | A reactor keeps | `react($post, 'love')` after `like` |
| --- | --- | --- |
| `multiple` (default) | one reaction per type | adds `love`; both stay |
| `single` | one reaction | replaces `like` with `love` |

Repeating the same type is a no-op in both modes and returns `false`.

### Set the mode

Set the default for the whole application:

```php title="config/pharaonic/reacts.php"
'mode' => 'single',
```

Or let a reactable decide with `ResolvesReactionMode`. The mode belongs to the reactable, never to the reactor:

```php title="app/Post.php"
namespace App;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionMode;

class Post extends Model implements ResolvesReactionMode
{
    use HasReactions;

    public function reactionMode(): string
    {
        return 'single';
    }
}
```

`reactionMode()` must return `single` or `multiple`, and the same answer for the same stored model on every server. Never take it from the request.

### Single mode

```php
$user->react($post, 'like');           // true: created
$user->react($post, 'love');           // true: like replaced by love, one row
$user->react($post, 'love');           // false: unchanged
$user->hasReacted($post, 'like');      // false
$user->toggleReaction($post, 'wow');   // true: love replaced by wow
$user->toggleReaction($post, 'wow');   // false: removed
$user->unreact($post, 'like');         // false: unreact never removes another type
```

A replacement is one change: the stored row takes the new type, and one `ReactionReplaced` event is dispatched, not a removal and a creation.

### Changing the mode later

Changing the mode does not rewrite stored rows, and reads never change them: counts and `reactionsOn()` show what is stored. The next write on a pair brings it in line:

- after a switch to `single`, the next `react()` keeps one reaction and removes the others in the same transaction, with one `ReactionReplaced`;
- after a switch to `multiple`, the next write simply allows a second type.

To clean up every pair at once, see [Integrity and Concurrency](#integrity-and-concurrency).
