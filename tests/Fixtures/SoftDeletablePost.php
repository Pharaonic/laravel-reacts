<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;

class SoftDeletablePost extends Model
{
    use HasReactions;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
