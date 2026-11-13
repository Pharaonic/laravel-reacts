<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;

/**
 * The default group, no contracts.
 */
class Comment extends Model
{
    use HasReactions;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
