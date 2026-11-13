---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory, then `php artisan migrate`.

## What does {package.name} require?

The latest release requires {package.requiresText}.

## Where are the reaction types stored?

In `config/pharaonic/reacts.php`. The database holds only the `reactions` table: each row is one reaction, with a registered key such as `love` or a free-form emoji such as `🚀`.

## Can a user react more than once to the same post?

In `multiple` mode, the default, a user can add several different types, but never the same one twice. In `single` mode, per model or for the whole app, a new type replaces the previous one.

## How is this different from Laravel Likes?

Laravel Likes stores one yes-or-no like per pair. {package.name} stores typed reactions and emoji. Both packages can be installed side by side; they keep separate tables.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, configuration, and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), ask in [GitHub Discussions](https://github.com/orgs/Pharaonic/discussions), or chat with us on [Discord](https://discord.gg/XQG9RhvEvf).
