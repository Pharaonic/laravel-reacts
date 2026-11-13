<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\ValueObjects;

/**
 * Where a reaction is being added: the reactable's group and mode. Immutable.
 */
final class ReactionContext
{
    public function __construct(
        private string $group,
        private string $mode,
    ) {
    }

    public function group(): string
    {
        return $this->group;
    }

    /**
     * "single" or "multiple".
     */
    public function mode(): string
    {
        return $this->mode;
    }
}
