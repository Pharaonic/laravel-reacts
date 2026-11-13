<h2>{{ $post->name }}</h2>
<p>Total reactions: {{ $reactionView['total'] }}</p>

<div class="reactions" aria-label="Post reactions">
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
</div>

@foreach ($reactionView['used_other'] as $reaction)
    <span class="used-other">{{ $reaction['emoji'] ?: $reaction['label'] }} ({{ $reaction['count'] }})</span>
@endforeach

@if ($reactionView['free_form_enabled'] && auth()->check())
    <form method="POST" action="{{ route('posts.reactions.toggle', $post) }}">
        @csrf
        <label for="free-form-emoji">Emoji</label>
        <input id="free-form-emoji" name="type" placeholder="🚀" required>
        <button type="submit">React</button>
    </form>
@endif
