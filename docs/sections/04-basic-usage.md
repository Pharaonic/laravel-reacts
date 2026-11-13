## Basic Usage

### Add the traits

Add `CanReact` to the model that reacts. It does not have to be a user: a team, a bot or any other model works.

```php title="app/Models/User.php"
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Reacts\Concerns\CanReact;

class User extends Authenticatable
{
    use CanReact;
}
```

Add `HasReactions` to the model that receives reactions. It gives the model its `reactions()` relation, its counts and its query scope.

```php title="app/Models/Post.php"
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;

class Post extends Model
{
    use HasReactions;
}
```

A model can use both traits, for example users who react to each other.

### React

`react()` returns whether the stored state changed:

```php
$user->react($post, 'like');      // true: added
$user->react($post, 'love');      // true: a second type, in multiple mode
$user->react($post, 'love');      // false: already there, nothing written
$user->react($post, 'LOVE');      // false: keys are lowercased first
```

The type must be offered by the reactable's group, or `react()` throws `InvalidReactionType`. A free-form emoji works only in groups with `free_form` on:

```php
$user->react($chatMessage, '🚀');  // true
$user->react($post, '🚀');         // InvalidReactionType: the group does not allow free-form emoji
```

### Check and list

```php
$user->hasReacted($post);           // true: any type
$user->hasReacted($post, 'love');   // true: this type
$post->reactedBy($user, 'like');    // the same check, from the reactable

$user->reactionsOn($post);          // Collection of Reaction models, oldest first
$user->reactionsOn($post)->pluck('type_key'); // ['like', 'love']
```

Checks with an unsaved model return `false` and an empty collection.

### Remove and toggle

```php
$user->unreact($post, 'love');        // true: removed
$user->unreact($post, 'love');        // false: nothing to remove

$user->toggleReaction($post, 'wow');  // true: added
$user->toggleReaction($post, 'wow');  // false: removed

$user->clearReactions($post);         // 2: the number of reactions removed
```

`toggleReaction()` returns the new state of that type. `unreact()` and `toggleReaction()` also remove a type the group no longer offers, so old reactions never get stuck.

### From the reactable

`HasReactions` has the same writes from the other side:

```php
$post->reactBy($user, 'like');     // the same as $user->react($post, 'like')
$post->unreactBy($user, 'like');   // the same as $user->unreact($post, 'like')
```

### Counts

```php
$post->reactionCounts();   // ['like' => 10, 'love' => 5, '🚀' => 2]: most first, then by key
$post->topReaction();      // 'like', or null when there are none
```

Counts come from one grouped query, or from the loaded `reactions` relation when it is loaded. They include every stored row, also types a group no longer offers.

:::warning Writes need saved models
`react()`, `unreact()`, `toggleReaction()` and `clearReactions()` throw `InvalidArgumentException` when either model is not saved yet.
:::
