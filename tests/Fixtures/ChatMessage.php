<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionGroup;

/**
 * The chat group, which accepts free-form emoji.
 *
 * @property int $workspace_id
 */
class ChatMessage extends Model implements ResolvesReactionGroup
{
    use HasReactions;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['workspace_id' => 1];

    public function reactionGroup(): string
    {
        return 'chat';
    }
}
