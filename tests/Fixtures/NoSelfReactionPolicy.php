<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Contracts\ReactionPolicy;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionContext;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;

/**
 * Refuses self-reactions and "angry"; records what it was asked.
 */
class NoSelfReactionPolicy implements ReactionPolicy
{
    /**
     * @var list<array{0: string, 1: string, 2: string}>
     */
    public static array $calls = [];

    public function allows(Model $reactor, Model $reactable, ReactionDefinition $definition, ReactionContext $context): bool
    {
        self::$calls[] = [$definition->key(), $context->group(), $context->mode()];

        return ! $reactor->is($reactable) && $definition->key() !== 'angry';
    }
}
