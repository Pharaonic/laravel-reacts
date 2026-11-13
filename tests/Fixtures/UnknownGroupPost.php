<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionGroup;

/**
 * Returns a group that is not configured.
 */
class UnknownGroupPost extends Model implements ResolvesReactionGroup
{
    use HasReactions;

    protected $table = 'posts';

    public function reactionGroup(): string
    {
        return 'missing';
    }
}
