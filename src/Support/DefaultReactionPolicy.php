<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Support;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Contracts\ReactionPolicy;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionContext;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;

/**
 * Allows every reaction the catalog accepted, including self-reactions.
 *
 * It knows nothing about the signed-in user or the application's rules:
 * authorize reactions with a Gate or Policy before calling the package, or
 * bind a policy of your own in pharaonic.reacts.policy.
 */
class DefaultReactionPolicy implements ReactionPolicy
{
    public function allows(Model $reactor, Model $reactable, ReactionDefinition $definition, ReactionContext $context): bool
    {
        return true;
    }
}
