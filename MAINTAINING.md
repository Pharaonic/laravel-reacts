# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Reactions between any two Eloquent models: `CanReact` for the model that reacts, `HasReactions` for the model that receives reactions, one `reactions` table. A reaction is a registered type key from the configuration (`love`) or, in groups that allow it, one free-form emoji (`🚀`). A reactable keeps one reaction per reactor (`single` mode) or one per reactor and type (`multiple` mode).

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major. Each line's PHP range, `illuminate/*` constraint, and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit; later lines run Pest.

## Core library

None. Everything the package does is Eloquent glue, and the emoji check uses PHP's own PCRE.

## Public API invariants

Configuration keys (`pharaonic.reacts.*`), publish tags, and registered Blade, Artisan, macro, and validation names are public API.

- Configuration: `model`, `table`, `connection`, `definitions`, `default_group`, `groups` (each with `types`, `extends`, `free_form`), `mode` (`multiple` or `single`), `cleanup_on_delete`, `preserve_on_soft_delete`, `policy`.
- Publish tags: `pharaonic`, `laravel-reacts`, `pharaonic-config`, `reacts-config`, `pharaonic-migrations`, and `reacts-migrations`. No routes, views, commands or facades.
- `CanReact`: `givenReactions()`, `react()`, `unreact()`, `toggleReaction()`, `clearReactions()`, `hasReacted()`, `reactionsOn()`. `HasReactions`: `reactions()`, `reactBy()`, `unreactBy()`, `reactedBy()`, `reactionCounts()`, `topReaction()`, `scopeWhereReactedBy()`. Neither trait defines a method of the other, so a model can use both.
- `react()` returns whether the stored state changed (created, or replaced in single mode); `toggleReaction()` returns the type's new state; `unreact()` whether it removed a row; `clearReactions()` the number removed. Reads return false or an empty collection for an unsaved model; writes throw `InvalidArgumentException` for one.
- `Models\Reaction` with `reactor()`, `reactable()`, `scopeWhereReactor()`, `scopeWhereReactable()`, `scopeWhereType()`, and the columns `reactor_type`, `reactor_id`, `reactable_type`, `reactable_id`, `type_key`, `identity_hash`, `pair_hash`, `single_slot_hash`.
- Contracts: `ResolvesReactionGroup`, `ResolvesReactionMode`, `ReactionPolicy`. Value objects: `ReactionDefinition` (`key()`, `kind()`, `label()`, `emoji()`, `icon()`, `meta()`, `forEmoji()`, `kindOf()`), `ReactionContext` (`group()`, `mode()`).
- `Support\ReactionView::for()`, `::forMany()`, `toArray()`, and `Support\ReactionViewBatch::get()`, `has()`. The array shape (`mode`, `free_form_enabled`, `total`, `choices`, `used_other`, and the item keys `kind`, `key`, `label`, `emoji`, `icon`, `count`, `selected`, `selectable`) is public API.
- `Events\ReactionCreated` (`$reaction`, `$reactor`, `$reactable`), `Events\ReactionReplaced` (`$current`, `$previous`, `$reactor`, `$reactable`), `Events\ReactionRemoved` (`$reaction`, `$reactor`, `$reactable`), each dispatched only for a change the call made.
- Exceptions: `InvalidReactionType` (`InvalidArgumentException`), `ReactionNotAllowed` (`RuntimeException`), `InvalidReactionConfiguration` (`UnexpectedValueException`).
- `Support\*` other than `ReactionView`, `ReactionViewBatch` and `DefaultReactionPolicy`, `Catalog\*`, and `Relations\ReactionsMorphMany` are internal.

## Package-specific constraints

- Scope decided with the maintainer, narrower than the original brief: the database holds only `reactions`. Types and groups come from the configuration only: no database catalog, no catalog cache, no scopes, no per-model type lists. A reactable that needs its own types returns its own group from `reactionGroup()`.
- The migration file name `2026_11_13_000001_create_pharaonic_reactions_table.php` and its class `CreatePharaonicReactionsTable` never change. The class is named because `6.x` supports Laravel below 8.37. Later schema changes go in new migrations.
- The migration refuses to run over an existing table, and creates the table with the configured model's table name on the configured model's connection. On MySQL and MariaDB `type_key` gets the `_bin` collation of the connection charset: the default collations compare many different emoji as equal.
- Uniqueness lives in the table: `identity_hash` (SHA-256 of reactor, reactable and key) is unique, and `single_slot_hash` (the pair hash, set only on single-mode rows, NULL otherwise) is unique. There is no lock table. A write runs in `Connection::transaction($callback, 5)` and reads the pair's rows with `lockForUpdate()`. A single-mode replacement updates the newest locked row in place (new `type_key`, `identity_hash` and timestamps; same id) instead of delete + insert: delete + insert frees the slot, and on PostgreSQL eight concurrent writers then kept stealing it from each other past five rounds. An insert (only on a pair with no row, or a new type in multiple mode) runs in a savepoint; when a unique index refuses it, the write reads the pair again, up to 5 rounds.
- On MySQL and MariaDB the first read of a pair is a plain `exists()`: a locking read of a pair with no rows takes a gap lock, and concurrent first writers then deadlock on their inserts (CI, 8 workers). An empty pair goes straight to the insert; a pair with rows, and every later round, is read with `lockForUpdate()`.
- On SQLite a write first runs an UPDATE that matches no row, to take the database write lock: a transaction that reads first fails at once with "database is locked" instead of waiting.
- `kind` is not stored: an ASCII key is registered, anything else is an emoji (`ReactionDefinition::kindOf()`).
- Free-form emoji follow the UTS #51 sequence grammar through PCRE's Unicode properties (`\p{Emoji}`, `\p{Emoji_Presentation}`, `\p{Emoji_Modifier}`, `\p{Regional_Indicator}`, `\p{Extended_Pictographic}`), not the RGI list: no emoji data ships with the package. Those properties need PCRE2 10.40. PHP builds that bundle PCRE (Windows, the official Docker images) ship 10.35 with PHP 8.0, so free-form emoji throw `InvalidReactionConfiguration` there, while a configured definition's `emoji` falls back to a basic check (UTF-8, no ASCII, spaces or control characters) so registered types keep working. Builds linked to the system PCRE (Ubuntu, Debian packages) have it. Canonical form: emoji-presentation characters drop U+FE0F, text-presentation characters get it, keycaps always carry it.
- Tests that need free-form emoji call `requireEmojiProperties()` and are skipped on a PCRE before 10.40; `FallbackWithoutEmojiPropertiesTest` runs only there and checks that registered types keep working. Run the suite on both kinds of build when the emoji code changes (for example the `php:8.0` Docker image for the bundled PCRE).
- Every anchored regex uses the `D` modifier: `$` alone accepts a trailing newline.
- Events are dispatched right after the write's own transaction, so inside a caller's transaction they come before it commits (`ShouldDispatchAfterCommit` needs Laravel 10.30).
- Removal is a query-builder delete checked by affected rows, so Eloquent `deleting`/`deleted` events do not run for it. Cleanup on delete is a bulk delete without events or policy.
- The package's own reads ignore global scopes of a custom reaction model; the `reactions()` and `givenReactions()` relations are plain Eloquent and apply them.
- Ids are stored as strings (`varchar(64)`), so the relations compare the parent key as a string in `has()`, `whereHas()`, and `withCount()` (`ReactionsMorphMany`), as in laravel-bookmarks.
- `phpstan.neon.dist` also analyses `tests/Fixtures`: PHPStan analyses a trait only through a class that uses it.
- `ConcurrencyTest` runs separate PHP processes (`tests/Processes/worker.php`) against a shared database: a SQLite file by default, MySQL or PostgreSQL through the `REACTS_DB*` environment variables (`.github/workflows/databases.yml`).
- The published-migration test needs a process where the migration class was never declared. `6.x` runs it as a PHPUnit class in separate processes; Pest cannot, so later lines run `tests/Processes/published-migration.php` as a subprocess.

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/reacts

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
