@foreach ($posts as $post)
    @php($reactions = $reactionViews->get($post))
    <article>
        <h3>{{ $post->name }}</h3>
        @foreach ($reactions['choices'] as $reaction)
            <span>{{ $reaction['emoji'] ?: $reaction['label'] }}: {{ $reaction['count'] }}</span>
        @endforeach
    </article>
@endforeach
