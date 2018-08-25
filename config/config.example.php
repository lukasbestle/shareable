<?php

return [
    /**
     * Debugging mode; if true, all Exceptions are output to the client
     * OPTIONAL, defaults to false
     */
    'debug' => false,

    /**
     * Base URL for file downloads; needs to point to the "files" directory
     * REQUIRED
     */
    'fileUrl' => 'https://cdn.example.com',

    /**
     * Paths to the data directories; you can move those directories to wherever you like
     * REQUIRED
     */
    'paths' => [
        // Directory where published files are stored
        // REQUIRED
        'files' => dirname(__DIR__) . '/data/files',

        // Directory where uploaded files are stored before publishing
        // REQUIRED
        'inbox' => dirname(__DIR__) . '/data/inbox',

        // Directory where metadata about the published files is stored
        // REQUIRED
        'items' => dirname(__DIR__) . '/data/items'
    ],

    /**
     * Custom routes
     * OPTIONAL
     */
    'routes' => [
        // Custom home page redirect
        [
            'pattern' => '',
            'method'  => 'GET',
            'action'  => function () {
                return Response::redirect('https://example.com');
            }
        ]
    ],

    /**
     * Users for the admin interface
     * OPTIONAL
     */
    'users' => [
        // User without login;
        // you can give it permissions if you like, but be careful!
        'anonymous' => [
            'permissions' => false
        ],

        // Custom users with login
        'your-user' => [
            'password' => '...', // Hashed with password_hash()
            'permissions' => [
                'upload',
                'publish',
                'delete',
                'meta'
            ]
        ]
    ]
];
