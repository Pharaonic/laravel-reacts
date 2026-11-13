<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Pharaonic\Laravel\Reacts\Models\Reaction;

/**
 * Sets its own table and connection, which win over the settings.
 */
class ExplicitReaction extends Reaction
{
    protected $table = 'explicit_reactions';

    protected $connection = 'secondary';
}
