<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\CanReact;

/**
 * A reactor that is not a user.
 */
class Bot extends Model
{
    use CanReact;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
