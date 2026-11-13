<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reaction model and table
    |--------------------------------------------------------------------------
    |
    | model: the package Reaction model or a subclass of it. A subclass that
    | sets $table or $connection uses that instead of the settings below.
    |
    | table: the table the migration creates. Set it before migrating:
    | changing it later does not rename an existing table.
    |
    | connection: null for the default connection, or a name in
    | database.connections.
    |
    */

    'model' => \Pharaonic\Laravel\Reacts\Models\Reaction::class,

    'table' => 'reactions',

    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Reaction types
    |--------------------------------------------------------------------------
    |
    | definitions: every registered type, keyed by a lowercase key (a letter,
    | then up to 63 letters, digits, "_" or "-"). A label is required; emoji
    | (one emoji), icon (a relative path or an https URL) and meta (an array
    | with string keys) are optional. They only describe how a type looks: a
    | stored reaction is its key, so changing them never rewrites reactions.
    |
    */

    'definitions' => [
        'like' => ['label' => 'Like', 'emoji' => '👍'],
        'love' => ['label' => 'Love', 'emoji' => '❤️'],
        'laugh' => ['label' => 'Laugh', 'emoji' => '😂'],
        'wow' => ['label' => 'Wow', 'emoji' => '😮'],
        'sad' => ['label' => 'Sad', 'emoji' => '😢'],
        'angry' => ['label' => 'Angry', 'emoji' => '😡'],
        'fire' => ['label' => 'Fire', 'emoji' => '🔥'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    |
    | The type lists reactables use. A group may extend another (it gets the
    | parent's types first), add types, restyle them with overrides, and hide
    | them with except, which nothing can bring back. free_form: true accepts
    | one emoji of the reactor's choice ("🚀"), stored in the reactions table
    | like any other reaction; null takes the nearest parent's setting.
    |
    | default_group is the group of a reactable without reactionGroup().
    |
    */

    'default_group' => 'default',

    'groups' => [
        'default' => [
            'types' => ['like', 'love'],
        ],
        'posts' => [
            'extends' => 'default',
            'types' => ['laugh', 'wow', 'sad', 'angry'],
        ],
        'chat' => [
            'types' => ['love', 'laugh', 'fire'],
            'free_form' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Behavior
    |--------------------------------------------------------------------------
    |
    | mode: "multiple" keeps one reaction per reactor, reactable and type;
    | "single" keeps one per reactor and reactable, so a new type replaces the
    | old one. A reactable implementing ResolvesReactionMode overrides it.
    | Stored rows are not rewritten when it changes: the next write on a pair
    | brings that pair in line with the new mode.
    |
    | cleanup_on_delete: delete the reactions a model gave or received when it
    | is deleted. A soft delete keeps them unless preserve_on_soft_delete is
    | false; a force delete always removes them.
    |
    | policy: a class implementing ReactionPolicy, asked before a reaction is
    | added. The default allows every reaction the catalog accepts.
    |
    */

    'mode' => 'multiple',

    'cleanup_on_delete' => true,

    'preserve_on_soft_delete' => true,

    'policy' => \Pharaonic\Laravel\Reacts\Support\DefaultReactionPolicy::class,

];
