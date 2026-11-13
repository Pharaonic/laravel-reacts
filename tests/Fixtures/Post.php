<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionGroup;

/**
 * The posts group, in the configured (multiple) mode.
 */
class Post extends Model implements ResolvesReactionGroup
{
    use HasReactions;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    public function reactionGroup(): string
    {
        return 'posts';
    }
}
