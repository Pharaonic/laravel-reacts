## Installation

Install the package with Composer and create its table.

### Requirements

- PHP 8.3, 8.4 or 8.5
- Laravel 13
- MySQL, MariaDB, PostgreSQL or SQLite
- PCRE2 10.40 or later for free-form emoji (PHP's bundled PCRE on most current builds)

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-reacts
```

The service provider is registered automatically.

### Run Migrations

The package loads its migration automatically, so the `reactions` table is created with your other migrations:

```bash title="Terminal" no-line-numbers
php artisan migrate
```

To change the migration before it runs, publish it into `database/migrations`:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=reacts-migrations
```

The published file keeps the package's file name, `2026_11_13_000001_create_pharaonic_reactions_table.php`, so Laravel runs your copy instead of the package's, never both.

:::warning Existing reactions table
The migration stops with an error when the table already exists. Set `pharaonic.reacts.table` to another name before migrating, or move that table out of the way first.
:::

### Publish Configuration

Publish the configuration to define your own reaction types and groups:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=reacts-config
```

The file is published to `config/pharaonic/reacts.php`.

:::success Installation Complete
You're all set! Add `CanReact` and `HasReactions` to your models, as [Basic Usage](#basic-usage) shows.
:::
