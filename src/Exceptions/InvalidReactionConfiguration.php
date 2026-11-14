<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Exceptions;

use UnexpectedValueException;

/**
 * A pharaonic.reacts setting, or a value a model contract returned, that the
 * package cannot work with: unknown groups, inheritance cycles, model classes,
 * table or connection names, cache settings, modes, or definition shapes.
 */
class InvalidReactionConfiguration extends UnexpectedValueException {}
