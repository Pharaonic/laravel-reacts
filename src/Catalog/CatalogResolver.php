<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Reacts\Catalog;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionGroup;
use Pharaonic\Laravel\Reacts\Contracts\ResolvesReactionMode;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionConfiguration;
use Pharaonic\Laravel\Reacts\Exceptions\InvalidReactionType;
use Pharaonic\Laravel\Reacts\Support\Settings;
use Pharaonic\Laravel\Reacts\ValueObjects\ReactionDefinition;

/**
 * The reaction types each group offers, from pharaonic.reacts.definitions and
 * pharaonic.reacts.groups. A group gets its parent's types first, then its
 * own; free_form comes from the nearest group that sets it.
 *
 * The configuration is validated once per fingerprint; nothing here queries
 * the database.
 *
 * @internal
 */
final class CatalogResolver
{
    private const GROUP_FIELDS = ['extends', 'types', 'free_form'];

    /**
     * The resolved groups and definitions, for the configuration they came from.
     *
     * @var array{fingerprint: string, definitions: array<string, ReactionDefinition>, groups: array<string, ResolvedCatalog>}|null
     */
    private static ?array $resolved = null;

    /**
     * The catalog of a reactable's group.
     *
     * @throws InvalidReactionConfiguration when the configuration or reactionGroup() is invalid
     */
    public static function forModel(Model $reactable): ResolvedCatalog
    {
        $group = $reactable instanceof ResolvesReactionGroup ? $reactable->reactionGroup() : Settings::defaultGroup();
        $groups = self::config()['groups'];

        if (! isset($groups[$group])) {
            throw new InvalidReactionConfiguration(sprintf(
                'The reaction group %s of [%s] is not configured in pharaonic.reacts.groups.', Settings::describe($group), get_class($reactable)
            ));
        }

        return $groups[$group];
    }

    /**
     * A configured definition, selectable in a group or not; null for an unknown key.
     */
    public static function definition(string $key): ?ReactionDefinition
    {
        return self::config()['definitions'][$key] ?? null;
    }

    /**
     * The mode of a reactable: its reactionMode(), or pharaonic.reacts.mode.
     *
     * @throws InvalidReactionConfiguration when the mode is not "single" or "multiple"
     */
    public static function modeOf(Model $reactable): string
    {
        if ($reactable instanceof ResolvesReactionMode) {
            return Settings::assertMode($reactable->reactionMode(), get_class($reactable).'::reactionMode()');
        }

        return Settings::mode();
    }

    /**
     * The definition a new reaction uses.
     *
     * @throws InvalidReactionType when the group does not offer the type
     */
    public static function definitionFor(ResolvedCatalog $catalog, string $key): ReactionDefinition
    {
        if (ReactionDefinition::kindOf($key) === 'emoji') {
            if (! $catalog->freeForm) {
                throw new InvalidReactionType(sprintf(
                    'The [%s] reaction group does not allow free-form emoji, so %s cannot be used.', $catalog->group, $key
                ));
            }

            return ReactionDefinition::forEmoji($key);
        }

        if (! isset($catalog->definitions[$key])) {
            throw new InvalidReactionType(sprintf('The reaction type [%s] is not selectable in the [%s] group.', $key, $catalog->group));
        }

        return $catalog->definitions[$key];
    }

    /**
     * @return array{fingerprint: string, definitions: array<string, ReactionDefinition>, groups: array<string, ResolvedCatalog>}
     *
     * @throws InvalidReactionConfiguration when the configuration is invalid
     */
    private static function config(): array
    {
        $rawDefinitions = Settings::get('definitions', []);
        $rawGroups = Settings::get('groups', []);
        $defaultGroup = Settings::defaultGroup();
        $fingerprint = hash('sha256', serialize([$rawDefinitions, $rawGroups, $defaultGroup]));

        if (self::$resolved !== null && self::$resolved['fingerprint'] === $fingerprint) {
            return self::$resolved;
        }

        if (! is_array($rawDefinitions) || ! is_array($rawGroups)) {
            throw new InvalidReactionConfiguration('The pharaonic.reacts.definitions and pharaonic.reacts.groups settings must be arrays.');
        }

        $definitions = [];

        foreach ($rawDefinitions as $key => $definition) {
            $key = self::key($key, 'The pharaonic.reacts.definitions key');

            try {
                $display = DefinitionNormalizer::definition($definition, sprintf('The pharaonic.reacts.definitions.%s definition', $key));
            } catch (InvalidReactionType $exception) {
                throw new InvalidReactionConfiguration($exception->getMessage(), 0, $exception);
            }

            $definitions[$key] = new ReactionDefinition($key, $display['label'], $display['emoji'], $display['icon'], $display['meta']);
        }

        $groups = [];

        foreach ($rawGroups as $name => $settings) {
            $name = self::key($name, 'The pharaonic.reacts.groups key');
            $groups[$name] = self::group($name, $settings, $rawGroups, $definitions);
        }

        if (! isset($groups[$defaultGroup])) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.default_group setting names [%s], which pharaonic.reacts.groups does not configure.', $defaultGroup
            ));
        }

        $resolved = [];

        foreach (array_keys($groups) as $name) {
            $resolved[$name] = self::resolve($name, $groups, $definitions, []);
        }

        return self::$resolved = ['fingerprint' => $fingerprint, 'definitions' => $definitions, 'groups' => $resolved];
    }

    /**
     * One group's settings, validated.
     *
     * @param  array<mixed>  $rawGroups
     * @param  array<string, ReactionDefinition>  $definitions
     * @return array{extends: string|null, types: list<string>, free_form: bool|null}
     */
    private static function group(string $name, mixed $settings, array $rawGroups, array $definitions): array
    {
        $path = 'pharaonic.reacts.groups.'.$name;

        if (! is_array($settings)) {
            throw new InvalidReactionConfiguration(sprintf('The %s setting must be an array.', $path));
        }

        $unknown = array_diff(array_map('strval', array_keys($settings)), self::GROUP_FIELDS);

        if ($unknown !== []) {
            throw new InvalidReactionConfiguration(sprintf(
                'The %s setting has unknown fields (%s); use %s.', $path, implode(', ', $unknown), implode(', ', self::GROUP_FIELDS)
            ));
        }

        $extends = $settings['extends'] ?? null;

        if ($extends !== null && (! is_string($extends) || ! array_key_exists($extends, $rawGroups))) {
            throw new InvalidReactionConfiguration(sprintf('The %s.extends setting names no configured group: %s.', $path, Settings::describe($extends)));
        }

        $rawTypes = $settings['types'] ?? [];

        if (! is_array($rawTypes)) {
            throw new InvalidReactionConfiguration(sprintf('The %s.types setting must be a list of type keys.', $path));
        }

        $types = [];

        foreach ($rawTypes as $key) {
            $key = self::key($key, 'The '.$path.'.types entry');

            if (! isset($definitions[$key])) {
                throw new InvalidReactionConfiguration(sprintf('The %s.types setting lists [%s], which pharaonic.reacts.definitions does not define.', $path, $key));
            }

            $types[] = $key;
        }

        $freeForm = $settings['free_form'] ?? null;

        if ($freeForm !== null && ! is_bool($freeForm)) {
            throw new InvalidReactionConfiguration(sprintf('The %s.free_form setting must be true, false, or null.', $path));
        }

        return ['extends' => $extends, 'types' => $types, 'free_form' => $freeForm];
    }

    /**
     * A group's catalog: its parent's types, then its own.
     *
     * @param  array<string, array{extends: string|null, types: list<string>, free_form: bool|null}>  $groups
     * @param  array<string, ReactionDefinition>  $definitions
     * @param  list<string>  $visiting  the groups below this one in the chain, to find cycles
     */
    private static function resolve(string $name, array $groups, array $definitions, array $visiting): ResolvedCatalog
    {
        if (in_array($name, $visiting, true)) {
            throw new InvalidReactionConfiguration(sprintf(
                'The pharaonic.reacts.groups inheritance has a cycle: %s.', implode(' → ', array_merge($visiting, [$name]))
            ));
        }

        $settings = $groups[$name];
        $parent = $settings['extends'] === null ? null : self::resolve($settings['extends'], $groups, $definitions, array_merge($visiting, [$name]));
        $types = $parent === null ? [] : $parent->definitions;

        foreach ($settings['types'] as $key) {
            $types[$key] = $definitions[$key];
        }

        return new ResolvedCatalog($name, $settings['free_form'] ?? ($parent !== null && $parent->freeForm), $types);
    }

    /**
     * @throws InvalidReactionConfiguration when the value is not a registered key
     */
    private static function key(mixed $key, string $source): string
    {
        if (! is_string($key) || preg_match(Settings::KEY_PATTERN, $key) !== 1) {
            throw new InvalidReactionConfiguration(sprintf(
                '%s %s is not a valid type key: a lowercase letter followed by up to 63 letters, digits, "_" or "-".',
                $source, Settings::describe($key)
            ));
        }

        return $key;
    }
}
