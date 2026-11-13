---
view: components.packages.quick-look
title: A quick look
subtitle: React, toggle and list reactions, with the counts and the viewer's choice in one array.
file: routes/web.php
language: php
code: |
  use App\Models\Post;
  use Pharaonic\Laravel\Reacts\Support\ReactionView;

  Route::post('posts/{post}/react', function (Post $post) {
      request()->user()->toggleReaction($post, request('type')); // true: added, false: removed

      return back();
  })->middleware('auth');

  Route::get('posts/{post}/reactions', function (Post $post) {
      return ReactionView::for($post, request()->user())->toArray();
      // ['mode' => 'multiple', 'total' => 12, 'choices' => [...], 'used_other' => [...]]
  });
---
