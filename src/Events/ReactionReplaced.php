<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Models\Reaction;

/**
 * Dispatched once when, in single mode, react(), reactBy() or toggleReaction()
 * replaces the pair's reaction with another type, right after the write's
 * own transaction commits. No ReactionCreated or ReactionRemoved accompanies it.
 *
 * $previous holds the removed rows (exists = false): one in the normal case,
 * several when the pair still had rows from multiple mode.
 */
final class ReactionReplaced
{
    /**
     * @param  list<Reaction>  $previous
     */
    public function __construct(
        public Reaction $current,
        public array $previous,
        public Model $reactor,
        public Model $reactable,
    ) {
    }
}
