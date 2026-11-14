<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Catalog;

use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;

/**
 * The reaction types of one group, in catalog order, and whether it accepts free-form emoji.
 *
 * @internal
 */
final class ResolvedCatalog
{
    /**
     * @param  array<string, ReactionDefinition>  $definitions
     */
    public function __construct(
        public string $group,
        public bool $freeForm,
        public array $definitions,
    ) {}
}
