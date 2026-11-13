## Reaction Types and Groups

A reaction is stored as its key: a registered key such as `love`, or one free-form emoji such as `🚀`. Labels, emoji and icons only describe how a type looks, so you can change them at any time without touching stored reactions.

### Definitions

`definitions` lists every registered type. A key is a lowercase letter followed by up to 63 lowercase letters, digits, `_` or `-`.

```php title="config/pharaonic/reacts.php"
'definitions' => [
    'like' => ['label' => 'Like', 'emoji' => '👍'],
    'insightful' => ['label' => 'Insightful', 'emoji' => '💡', 'meta' => ['color' => 'amber']],
    'party_cat' => ['label' => 'Party Cat', 'icon' => '/images/reactions/party-cat.webp'],
],
```

| Field | Required | Description |
| --- | --- | --- |
| `label` | Yes | Plain text, 1 to 100 characters, no control characters. |
| `emoji` | No | Exactly one emoji, stored in canonical form. |
| `icon` | No | A relative path or an `https` URL of up to 1024 characters. `javascript:`, `data:`, `http:` and `//host` are refused. |
| `meta` | No | An array with string keys, for your own presentation data. |

:::warning Icons are references
The package stores and returns the icon string; it never uploads, resizes or checks the file. Serve trusted files, and keep Blade's escaping on when you render labels and icons.
:::

### Groups

A group is the list of types one kind of content offers. A group may `extend` another: it offers the parent's types first, then its own.

```php title="config/pharaonic/reacts.php"
'groups' => [
    'default' => ['types' => ['like', 'love']],
    'posts' => ['extends' => 'default', 'types' => ['laugh', 'wow', 'sad', 'angry']],
    'chat' => ['types' => ['love', 'laugh', 'fire'], 'free_form' => true],
],
```

Here posts offer `like`, `love`, `laugh`, `wow`, `sad` and `angry`, in that order.

A reactable uses `default_group` unless it implements `ResolvesReactionGroup`:

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

`reactionGroup()` may depend on the model, for example `return $this->is_announcement ? 'posts' : 'chat';`.

### Free-form emoji

In a group with `'free_form' => true`, a reactor may also react with one emoji of their choice. A group without the setting takes it from the group it extends; a group that sets nothing accepts no free-form emoji.

```php
$user->react($chatMessage, '🚀');           // true
$user->react($chatMessage, '👍🏽');          // true: skin tones, flags, keycaps and ZWJ sequences count as one emoji
$user->react($chatMessage, '🚀🔥');          // InvalidReactionType: two emoji
$user->react($chatMessage, 'nice');         // InvalidReactionType: text is never an emoji reaction
```

Free-form reactions do not need a definition: they are stored in the `reactions` table like any other reaction, with the emoji as the key.

Every spelling of an emoji is stored the same way: an emoji that shows as an emoji by default never keeps U+FE0F, and one that shows as text by default always gets it. So `❤` and `❤️` are one reaction, and so are `🚀` and `🚀` followed by U+FE0F.

:::info How emoji are checked
The package accepts one emoji sequence as Unicode's emoji grammar describes it, using the Unicode emoji properties of PHP's PCRE library. That needs PCRE2 10.40 or later (`PCRE_VERSION`). PHP builds that bundle an older PCRE, such as PHP 8.0 and 8.1 on Windows or in the official Docker images, throw `InvalidReactionConfiguration` for free-form emoji; registered types keep working, and the `emoji` of a definition gets a simpler check.
:::

### Removing a type

Take a type out of a group, or out of `definitions`, and new reactions with it are refused. Existing ones stay: they are still counted, `hasReacted()` still sees them, and `unreact()` and `toggleReaction()` still remove them. [Listing Reactions](#listing-reactions) shows them under `used_other`.
