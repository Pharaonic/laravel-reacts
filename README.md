<p align="center"><a href="https://pharaonic.dev/packages/laravel/reacts" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/laravel-reacts/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.2%20|%208.3%20|%208.4&color=blue&style=flat-square" alt="PHP Version : 8.2 | 8.3 | 8.4"></a>
  <a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/static/v1?label=Laravel&message=11.x&color=F05340&style=flat-square" alt="Laravel Version : 11.x"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/laravel-reacts/actions/workflows/build.yml/badge.svg?branch=11.x" alt="Build">
  <br>
  <a href="https://packagist.org/packages/Pharaonic/laravel-reacts" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/laravel-reacts&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-reacts" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-reacts/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-reacts" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-reacts/downloads" alt="Packagist Downloads"></a>
</p>

<h3 align="center">Reactions for Eloquent models: configured types, free-form emoji, single or multiple mode.</h3>
<br>

## Installation

```bash
composer require pharaonic/laravel-reacts
php artisan migrate
```

The service provider is registered automatically, and `migrate` creates the `reactions` table. Publish the configuration to define your own reaction types and groups:

```bash
php artisan vendor:publish --tag=reacts-config
```

## Usage

Add `CanReact` to the model that reacts and `HasReactions` to the model that receives reactions:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Reacts\Concerns\CanReact;

class User extends Authenticatable
{
    use CanReact;
}
```

```php
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Reacts\Concerns\HasReactions;

class Post extends Model
{
    use HasReactions;
}
```

```php
use Pharaonic\Laravel\Reacts\Support\ReactionView;

$user->react($post, 'like');            // true: added
$user->react($post, 'love');            // true: a second type (multiple mode, the default)
$user->react($post, 'love');            // false: already there
$user->toggleReaction($post, 'like');   // false: removed
$user->hasReacted($post, 'love');       // true
$user->react($chatMessage, '🚀');        // true: in a group that allows free-form emoji

$post->reactionCounts();                // ['love' => 1]
ReactionView::for($post, $user)->toArray(); // choices, counts and the viewer's selection, for Blade or JSON
```

## Documentation

Full documentation: [Laravel Reacts Documentation](https://pharaonic.dev/packages/laravel/reacts).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md). Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
