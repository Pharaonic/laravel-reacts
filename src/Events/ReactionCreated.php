<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Models\Reaction;

/**
 * Dispatched once for each reaction react(), reactBy() or toggleReaction()
 * inserts without replacing another, right after the write's own transaction commits.
 */
final class ReactionCreated
{
    public function __construct(
        public Reaction $reaction,
        public Model $reactor,
        public Model $reactable,
    ) {
    }
}
