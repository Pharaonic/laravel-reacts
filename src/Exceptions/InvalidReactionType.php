<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Exceptions;

use InvalidArgumentException;

/**
 * A reaction type the call cannot use: invalid syntax, an unknown or disabled
 * type, free-form emoji where the group does not allow it, a sequence that is
 * not one emoji, or catalog input that does not describe a valid type.
 */
class InvalidReactionType extends InvalidArgumentException {}
