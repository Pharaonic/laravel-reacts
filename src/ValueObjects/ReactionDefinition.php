<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\ValueObjects;

/**
 * How a reaction type looks. Immutable.
 *
 * The key is the reaction's identity: a registered key ("love") or a
 * free-form emoji ("🚀"). Everything else is presentation, so changing it
 * never changes or rewrites stored reactions.
 */
final class ReactionDefinition
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private string $key,
        private string $label,
        private ?string $emoji = null,
        private ?string $icon = null,
        private array $meta = [],
    ) {}

    /**
     * The definition of a free-form emoji reaction.
     */
    public static function forEmoji(string $emoji): self
    {
        return new self($emoji, $emoji, $emoji);
    }

    /**
     * The registered type key, or the canonical emoji.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * "registered" for a type key, "emoji" for a free-form emoji.
     */
    public function kind(): string
    {
        return self::kindOf($this->key);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function emoji(): ?string
    {
        return $this->emoji;
    }

    /**
     * An icon reference the application serves (a relative path or an https URL).
     */
    public function icon(): ?string
    {
        return $this->icon;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * "registered" for an ASCII type key, "emoji" for anything else.
     */
    public static function kindOf(string $key): string
    {
        return preg_match('/^[\x20-\x7E]+$/D', $key) === 1 ? 'registered' : 'emoji';
    }
}
