<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Temporary Upload Disk
    |--------------------------------------------------------------------------
    |
    | The disk where temporary uploads are stored before being attached
    | to a model. This should be a private disk.
    |
    */
    'temp_disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Temporary Upload Path
    |--------------------------------------------------------------------------
    |
    | The path within the temp disk where temporary uploads are stored.
    |
    */
    'temp_path' => 'temp',

    /*
    |--------------------------------------------------------------------------
    | Cleanup Hours
    |--------------------------------------------------------------------------
    |
    | The number of hours after which unattached temporary uploads
    | should be cleaned up.
    |
    */
    'cleanup_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the package routes.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => ['api'],
    ],
];
