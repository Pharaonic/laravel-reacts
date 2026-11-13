## Examples

### 1. Post reactions, one per user

Posts keep one reaction per user, while chat messages keep the default multiple mode and accept any emoji.

- ===Post model

  ```php title="app/Post.php"
  namespace App;

  use Illuminate\Database\Eloquent\Model;
  use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
  use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionGroup;
  use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionMode;

  class Post extends Model implements ResolvesReactionGroup, ResolvesReactionMode
  {
      use HasReactions;

      public function reactionGroup(): string
      {
          return 'posts';
      }

      public function reactionMode(): string
      {
          return 'single';
      }
  }
  ```

- ===Chat message model

  ```php title="app/ChatMessage.php"
  namespace App;

  use Illuminate\Database\Eloquent\Model;
  use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
  use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionGroup;

  class ChatMessage extends Model implements ResolvesReactionGroup
  {
      use HasReactions;

      public function reactionGroup(): string
      {
          return 'chat';
      }
  }
  ```

- ===Usage

  ```php
  $user->react($post, 'like');          // true
  $user->react($post, 'love');          // true: like replaced
  $user->react($message, 'fire');       // true
  $user->react($message, '🚀');         // true: free-form emoji, alongside fire
  ```

### 2. A custom reaction with an icon

```php title="config/pharaonic/reacts.php"
'definitions' => [
    // ...
    'party_cat' => ['label' => 'Party Cat', 'icon' => '/images/reactions/party-cat.webp'],
],

'groups' => [
    // ...
    'chat' => ['types' => ['love', 'laugh', 'fire', 'party_cat'], 'free_form' => true],
],
```

```php
$user->react($message, 'party_cat');
ReactionView::for($message)->toArray()['choices'][3]['icon']; // "/images/reactions/party-cat.webp"
```

### 3. "Who reacted with what"

```php
$reactions = $post->reactions()->with('reactor')->latest()->limit(20)->get();

foreach ($reactions as $reaction) {
    echo $reaction->reactor->name.' '.$reaction->type_key;
}
```

### 4. The posts a user loved

```php
$loved = Post::whereReactedBy($user, 'love')->latest()->paginate(15);
```

### 5. Notify the author of a new reaction

```php title="app/Providers/EventServiceProvider.php"
protected $listen = [
    \Pharaonic\Laravel\Reacts\Events\ReactionCreated::class => [\App\Listeners\NotifyAuthor::class],
    \Pharaonic\Laravel\Reacts\Events\ReactionReplaced::class => [\App\Listeners\NotifyAuthor::class],
];
```
