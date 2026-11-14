<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Models\Reaction;

/**
 * Dispatched once for each row unreact(), unreactBy(), toggleReaction() or
 * clearReactions() deletes, right after the write's own transaction commits. The
 * reaction has exists = false. Cleanup on delete dispatches none.
 */
final class ReactionRemoved
{
    public function __construct(
        public Reaction $reaction,
        public Model $reactor,
        public Model $reactable,
    ) {}
}
