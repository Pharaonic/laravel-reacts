<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

/**
 * A connection name as an enum, which Laravel accepts from 12.28.
 */
enum ReactionConnection: string
{
    case Secondary = 'secondary';
}
