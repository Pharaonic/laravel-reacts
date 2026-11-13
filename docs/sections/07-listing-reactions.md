## Listing Reactions

`ReactionView` turns the reactions of a reactable into an array you can pass to Blade or return as JSON. It never writes, and holds only strings, numbers and booleans.

```php
use Pharaonic\Laravel\Reacts\Support\ReactionView;

$view = ReactionView::for($post, auth()->user())->toArray();
```

The viewer may be `null`: then nothing is selected.

### The array

```json
{
  "mode": "single",
  "free_form_enabled": false,
  "total": 15,
  "choices": [
    {"kind": "registered", "key": "like", "label": "Like", "emoji": "👍", "icon": null, "count": 10, "selected": false, "selectable": true},
    {"kind": "registered", "key": "love", "label": "Love", "emoji": "❤️", "icon": null, "count": 5, "selected": true, "selectable": true},
    {"kind": "registered", "key": "wow", "label": "Wow", "emoji": "😮", "icon": null, "count": 0, "selected": false, "selectable": true}
  ],
  "used_other": []
}
```

| Key | Description |
| --- | --- |
| `mode` | The reactable's mode: `single` or `multiple`. |
| `free_form_enabled` | Whether the reactable's group accepts free-form emoji. |
| `total` | Every stored reaction row, also of types the group no longer offers. In multiple mode it can be more than the number of users. |
| `choices` | Every type the group offers, in configuration order, also with a count of 0. |
| `used_other` | Stored reactions that are not choices: free-form emoji, and types the group no longer offers. Most used first. |

Each item has `kind` (`registered` or `emoji`), `key`, `label`, `emoji`, `icon`, `count`, `selected` (the viewer has this reaction) and `selectable`. `selectable` means the group offers the type, or, for an emoji, that the group accepts free-form emoji; it is not an authorization decision. A type with no definition left gets a label made from its key (`party_cat` becomes "Party cat") and no emoji or icon.

### In Blade

The package has no routes; these are your application's.

```php title="routes/web.php"
Route::get('posts/{post}', 'PostReactionsController@show')->name('posts.show');
Route::post('posts/{post}/reactions/toggle', 'PostReactionsController@toggle')
    ->middleware('auth')->name('posts.reactions.toggle');
```

```php title="app/Http/Controllers/PostReactionsController.php"
namespace App\Http\Controllers;

use App\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Pharaonic\Laravel\Reacts\Support\ReactionView;

class PostReactionsController extends Controller
{
    public function show(Post $post, Request $request)
    {
        return view('posts.show', [
            'post' => $post,
            'reactionView' => ReactionView::for($post, $request->user())->toArray(),
        ]);
    }

    public function toggle(Post $post, Request $request)
    {
        Gate::authorize('react', $post); // your Gate or Policy
        $input = $request->validate(['type' => ['required', 'string', 'max:128']]);
        $request->user()->toggleReaction($post, $input['type']);

        return back();
    }
}
```

```blade title="resources/views/posts/show.blade.php"
<p>Total reactions: {{ $reactionView['total'] }}</p>

@foreach ($reactionView['choices'] as $reaction)
    <form action="{{ route('posts.reactions.toggle', $post) }}" method="POST">
        @csrf
        <input type="hidden" name="type" value="{{ $reaction['key'] }}">
        <button type="submit"
                aria-pressed="{{ $reaction['selected'] ? 'true' : 'false' }}"
                {{ ! auth()->check() || ! $reaction['selectable'] ? 'disabled' : '' }}>
            @if ($reaction['icon'])
                <img src="{{ $reaction['icon'] }}" alt="" width="20" height="20">
            @elseif ($reaction['emoji'])
                <span aria-hidden="true">{{ $reaction['emoji'] }}</span>
            @endif
            {{ $reaction['label'] }} ({{ $reaction['count'] }})
        </button>
    </form>
@endforeach

@foreach ($reactionView['used_other'] as $reaction)
    <span>{{ $reaction['emoji'] ?: $reaction['label'] }} ({{ $reaction['count'] }})</span>
@endforeach

@if ($reactionView['free_form_enabled'] && auth()->check())
    <form method="POST" action="{{ route('posts.reactions.toggle', $post) }}">
        @csrf
        <input name="type" placeholder="🚀" required>
        <button type="submit">React</button>
    </form>
@endif
```

Clicking a selected reaction removes it; clicking another adds it, or replaces the previous one in single mode. Disabled buttons are presentation only: the controller still authorizes every request.

### As JSON

```php title="routes/api.php"
use App\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Pharaonic\Laravel\Reacts\Support\ReactionView;

Route::get('posts/{post}/reactions', function (Post $post, Request $request) {
    return response()->json(['data' => ReactionView::for($post, $request->user())->toArray()]);
});

Route::post('posts/{post}/reactions', function (Post $post, Request $request) {
    Gate::authorize('react', $post);
    $request->user()->toggleReaction($post, $request->validate(['type' => 'required|string|max:128'])['type']);

    return response()->json(['data' => ReactionView::for($post, $request->user())->toArray()]);
})->middleware('auth:api');
```

The array holds counts and the viewer's own selection only, never other reactors' ids. Icons are strings, never files or HTML.

### A paginated feed

`ReactionView::forMany()` builds the views of many reactables in two queries, whatever the page size: one for every count, one for the viewer's reactions. Pass the paginator's items, not the paginator; models of different classes can be mixed.

```php title="app/Http/Controllers/PostController.php"
$posts = Post::query()->latest()->paginate(15);
$reactionViews = ReactionView::forMany($posts->items(), $request->user());

return view('posts.index', compact('posts', 'reactionViews'));
```

```blade title="resources/views/posts/index.blade.php"
@foreach ($posts as $post)
    @php($reactions = $reactionViews->get($post))
    <article>
        <h3>{{ $post->title }}</h3>
        @foreach ($reactions['choices'] as $reaction)
            <span>{{ $reaction['emoji'] ?: $reaction['label'] }}: {{ $reaction['count'] }}</span>
        @endforeach
    </article>
@endforeach
```

`get()` finds a model by its morph type and key, and throws `InvalidArgumentException` for a model that was not in the batch; `has()` checks first.
