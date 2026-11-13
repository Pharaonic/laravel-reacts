<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Exceptions;

use RuntimeException;

/**
 * The reaction policy refused a valid, selectable reaction.
 */
class ReactionNotAllowed extends RuntimeException
{
}
