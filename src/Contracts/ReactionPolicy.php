<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Contracts;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionContext;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;

/**
 * Decides whether a reactor may add a reaction the catalog already accepted.
 *
 * It runs inside the write's transaction, after the pair's rows are locked, only when the
 * call would add a reaction. A rule that reads other rows (such as a maximum
 * number of reactions) is only as safe as the locks that rule takes itself.
 */
interface ReactionPolicy
{
    public function allows(
        Model $reactor,
        Model $reactable,
        ReactionDefinition $definition,
        ReactionContext $context,
    ): bool;
}
