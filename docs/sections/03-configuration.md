## Configuration

Every setting lives in `config/pharaonic/reacts.php` and is read as `pharaonic.reacts.*`. The file holds only arrays, strings and booleans, so `php artisan config:cache` works.

```php title="config/pharaonic/reacts.php"
return [
    'model' => \Pharaonic\Laravel\Reacts\Models\Reaction::class,
    'table' => 'reactions',
    'connection' => null,

    'definitions' => [
        'like' => ['label' => 'Like', 'emoji' => '👍'],
        'love' => ['label' => 'Love', 'emoji' => '❤️'],
        'laugh' => ['label' => 'Laugh', 'emoji' => '😂'],
        'wow' => ['label' => 'Wow', 'emoji' => '😮'],
        'sad' => ['label' => 'Sad', 'emoji' => '😢'],
        'angry' => ['label' => 'Angry', 'emoji' => '😡'],
        'fire' => ['label' => 'Fire', 'emoji' => '🔥'],
    ],

    'default_group' => 'default',

    'groups' => [
        'default' => ['types' => ['like', 'love']],
        'posts' => ['extends' => 'default', 'types' => ['laugh', 'wow', 'sad', 'angry']],
        'chat' => ['types' => ['love', 'laugh', 'fire'], 'free_form' => true],
    ],

    'mode' => 'multiple',
    'cleanup_on_delete' => true,
    'preserve_on_soft_delete' => true,
    'policy' => \Pharaonic\Laravel\Reacts\Support\DefaultReactionPolicy::class,
];
```

### Settings

| Key | Default | Description |
| --- | --- | --- |
| `model` | `Models\Reaction` | The reaction model: the package's or a subclass of it. See [Custom Reaction Model](#custom-model). |
| `table` | `reactions` | The table the migration creates and the model uses. Set it before migrating. |
| `connection` | `null` | A connection in `database.connections`, or `null` for the default one. |
| `definitions` | 7 types | Every registered type, keyed by its key. See [Reaction Types and Groups](#reaction-types). |
| `default_group` | `default` | The group of a reactable that does not implement `ResolvesReactionGroup`. |
| `groups` | `default`, `posts`, `chat` | Which types each group offers, and whether it accepts free-form emoji. |
| `mode` | `multiple` | `multiple` or `single`. See [Single and Multiple Mode](#modes). |
| `cleanup_on_delete` | `true` | Delete the reactions a model gave or received when it is deleted. |
| `preserve_on_soft_delete` | `true` | Keep the reactions when a model is only soft deleted. |
| `policy` | `DefaultReactionPolicy` | A class implementing `ReactionPolicy`. See [Authorization and Policies](#authorization). |

### Validation

The package validates the configuration the first time it needs it and throws `InvalidReactionConfiguration` with the setting's name for an unknown group, an inheritance cycle, a type a group lists but no definition defines, a key that is not a valid type key, a definition without a label, an unsafe icon, an unknown field, a mode other than `single` or `multiple`, a model class that is not a `Reaction`, or a connection that does not exist.

:::info Merged configuration
Laravel merges the package configuration one level deep. When you publish the file, keep every key in it, and edit `definitions` and `groups` as whole arrays.
:::
