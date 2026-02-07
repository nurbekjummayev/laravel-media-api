<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Disk Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the disks for different storage types.
    | - temp: Temporary uploads before being attached
    | - public: Publicly accessible media files
    | - private: Private media files (requires signed URLs)
    |
    */
    'disks' => [
        'temp' => 'local',
        'public' => 'public',
        'private' => 'local',
    ],

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