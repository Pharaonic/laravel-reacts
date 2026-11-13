<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionGroup;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionMode;

/**
 * The posts group; its mode comes from a stored column.
 *
 * @property bool $single
 */
class StatefulPost extends Model implements ResolvesReactionGroup, ResolvesReactionMode
{
    use HasReactions;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = ['single' => 'boolean'];

    public function reactionGroup(): string
    {
        return 'posts';
    }

    public function reactionMode(): string
    {
        return $this->single ? 'single' : 'multiple';
    }
}
