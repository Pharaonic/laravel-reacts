## Authorization and Policies

The package does not know who is signed in or who may react to what. Authorize the request in your application first, with a Gate or Policy, before calling `react()` or `toggleReaction()`:

```php title="app/Providers/AuthServiceProvider.php"
Gate::define('react', function (User $user, Post $post) {
    return ! $user->is_banned && $post->is_published;
});
```

```php
Gate::authorize('react', $post);
$request->user()->toggleReaction($post, $request->input('type'));
```

### Reaction policy

For rules that belong to every reaction, whatever the controller, bind a `ReactionPolicy`. It is asked after the type passed the catalog check, inside the write's transaction, and only when the call would add a reaction: removing one never asks it.

```php title="app/Reactions/NoSelfReactions.php"
namespace App\Reactions;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Contracts\ReactionPolicy;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionContext;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;

class NoSelfReactions implements ReactionPolicy
{
    public function allows(Model $reactor, Model $reactable, ReactionDefinition $definition, ReactionContext $context): bool
    {
        return ! $reactor->is($reactable->author);
    }
}
```

```php title="config/pharaonic/reacts.php"
'policy' => \App\Reactions\NoSelfReactions::class,
```

The policy is resolved from the container, so its constructor can take dependencies. It receives:

| Argument | Description |
| --- | --- |
| `$definition` | `key()`, `kind()`, `label()`, `emoji()`, `icon()`, `meta()` of the type being added |
| `$context` | `group()` and `mode()` of the reactable |

When it returns `false`, the call throws `ReactionNotAllowed` and writes nothing; in single mode the previous reaction stays. The default policy, `DefaultReactionPolicy`, allows everything, including reacting to yourself.

:::warning Limits are not atomic
A policy that counts rows, such as "at most three reactions per post", reads before it decides, so two requests at the same moment can both pass. Take your own lock when such a limit must be exact.
:::
