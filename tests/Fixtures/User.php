<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Reacts\Concerns\CanReact;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;

/**
 * Reacts to other models and is reacted to too.
 */
class User extends Authenticatable
{
    use CanReact;
    use HasReactions;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
