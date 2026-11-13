<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionMode;

/**
 * A post that keeps one reaction per reactor.
 */
class SinglePost extends Post implements ResolvesReactionMode
{
    protected $table = 'posts';

    public function reactionMode(): string
    {
        return 'single';
    }
}
