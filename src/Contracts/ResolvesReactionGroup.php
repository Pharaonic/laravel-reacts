<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Contracts;

/**
 * A reactable that reacts with the types of a catalog group other than the default one.
 */
interface ResolvesReactionGroup
{
    /**
     * A group configured in pharaonic.reacts.groups.
     */
    public function reactionGroup(): string;
}
