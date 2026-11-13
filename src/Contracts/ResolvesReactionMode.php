<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Contracts;

/**
 * A reactable that overrides pharaonic.reacts.mode.
 */
interface ResolvesReactionMode
{
    /**
     * "single" (one reaction per reactor) or "multiple" (one per reactor and type).
     * It must give the same answer for the same stored model on every worker.
     */
    public function reactionMode(): string;
}
