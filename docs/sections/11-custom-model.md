## Custom Reaction Model

Use your own model, for example to add relations or accessors. It must extend the package's `Reaction`:

```php title="app/Reaction.php"
namespace App;

use Pharaonic\Laravel\Reacts\Models\Reaction as BaseReaction;

class Reaction extends BaseReaction
{
    public function isEmoji(): bool
    {
        return \Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition::kindOf($this->type_key) === 'emoji';
    }
}
```

```php title="config/pharaonic/reacts.php"
'model' => \App\Reaction::class,
'table' => 'social_reactions',
'connection' => 'mysql',
```

Every query, relation and returned reaction uses your class: `reactionsOn()`, `reactions()`, `givenReactions()`, events and the scopes.

### Table and connection

The base model takes its table from `table` and its connection from `connection`. A subclass that sets `$table` or `$connection` itself wins over both, and the migration creates the table with that name, on that connection.

:::warning Set them before migrating
The migration creates the table it finds in the configuration when it runs. Renaming `table` afterwards does not rename the existing table.
:::
