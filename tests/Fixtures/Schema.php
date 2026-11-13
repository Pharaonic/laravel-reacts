<?php

namespace Pharaonic\Laravel\Reacts\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * The fixture tables. The reactions tables come from the package migrations.
 */
final class Schema
{
    public static function create(): void
    {
        foreach (['users', 'bots', 'posts', 'comments'] as $name) {
            SchemaFacade::create($name, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        SchemaFacade::create('chat_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('workspace_id')->default(1);
            $table->timestamps();
        });

        SchemaFacade::create('articles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kind')->default('news');
            $table->timestamps();
        });

        SchemaFacade::create('stateful_posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('single')->default(false);
            $table->timestamps();
        });

        foreach (['soft_deletable_posts', 'soft_deletable_members'] as $name) {
            SchemaFacade::create($name, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        SchemaFacade::create('uuid_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
        });

        SchemaFacade::create('ulid_models', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->timestamps();
        });
    }
}
