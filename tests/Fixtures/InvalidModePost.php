<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionMode;

/**
 * Returns a mode that does not exist.
 */
class InvalidModePost extends Model implements ResolvesReactionMode
{
    use HasReactions;

    protected $table = 'posts';

    public function reactionMode(): string
    {
        return 'many';
    }
}
