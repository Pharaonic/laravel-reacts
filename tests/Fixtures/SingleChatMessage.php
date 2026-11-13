<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionMode;

/**
 * A chat message that keeps one reaction per reactor.
 */
class SingleChatMessage extends ChatMessage implements ResolvesReactionMode
{
    protected $table = 'chat_messages';

    public function reactionMode(): string
    {
        return 'single';
    }
}
