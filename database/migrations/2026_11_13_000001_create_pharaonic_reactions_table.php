<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Reacts\Support\ReactionModelResolver;

/**
 * One row per reaction, registered ("love") or free-form emoji ("🚀"). Ids
 * are strings, so integer, UUID and ULID keys can share the table.
 *
 * identity_hash keeps one row per reactor, reactable and type.
 * single_slot_hash, set only on single-mode rows, keeps one such row per
 * reactor and reactable. The table goes on the configured model's connection.
 */
class CreatePharaonicReactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $model = ReactionModelResolver::reaction();
        $name = $model->getTable();
        $connection = $model->getConnection();
        $schema = Schema::connection($model->getConnectionName());

        if ($schema->hasTable($name)) {
            throw new RuntimeException(sprintf(
                'The [%s] table already exists, so pharaonic/laravel-reacts cannot create it. '
                .'Set pharaonic.reacts.table to another name, or move the existing table out of the way.',
                $name
            ));
        }

        $schema->create($name, function (Blueprint $table) use ($name, $connection) {
            $table->bigIncrements('id');
            $table->string('reactor_type', 191);
            $table->string('reactor_id', 64);
            $table->string('reactable_type', 191);
            $table->string('reactable_id', 64);
            // The registered key ("love"), or the free-form emoji ("🚀").
            $typeKey = $table->string('type_key', 191);
            $table->char('identity_hash', 64);
            $table->char('pair_hash', 64);
            $table->char('single_slot_hash', 64)->nullable();
            $table->timestamps();

            // MySQL's case- and accent-insensitive collations treat many
            // different emoji as equal; counts must tell them apart.
            if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                $charset = $connection->getConfig('charset');
                $typeKey->collation((is_string($charset) && $charset !== '' ? $charset : 'utf8mb4').'_bin');
            }

            $table->unique('identity_hash', $name.'_identity_unique');
            $table->unique('single_slot_hash', $name.'_single_slot_unique');
            $table->index('pair_hash', $name.'_pair_index');
            $table->index(['reactable_type', 'reactable_id', 'type_key'], $name.'_reactable_index');
            $table->index(['reactor_type', 'reactor_id'], $name.'_reactor_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $model = ReactionModelResolver::reaction();

        Schema::connection($model->getConnectionName())->dropIfExists($model->getTable());
    }
}
