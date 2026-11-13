<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View as ViewFactory;
use Pharaonic\Laravel\Reacts\Support\ReactionView;

/**
 * The README's host-application controller.
 */
class PostReactionsController
{
    public function show(Post $post, Request $request): View
    {
        return ViewFactory::make('posts.show', [
            'post' => $post,
            'reactionView' => ReactionView::for($post, $this->user($request))->toArray(),
        ]);
    }

    public function index(Request $request): View
    {
        $posts = Post::query()->orderBy('id')->paginate(15);
        $reactionViews = ReactionView::forMany($posts->items(), $this->user($request));

        return ViewFactory::make('posts.index', compact('posts', 'reactionViews'));
    }

    public function toggle(Post $post, Request $request): RedirectResponse
    {
        Gate::authorize('react', $post);
        $input = $request->validate(['type' => ['required', 'string', 'max:128']]);
        $type = $input['type'] ?? '';
        $this->user($request)?->toggleReaction($post, is_string($type) ? $type : '');

        return back();
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
