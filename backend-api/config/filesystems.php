<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         | Supabase Storage — S3-compatible bucket via the AWS SDK.
         | Set FILESYSTEM_DISK=supabase in .env to use this as default.
         */
        'supabase' => [
            'driver'                  => 's3',
            'key'                     => env('SUPABASE_KEY'),
            'secret'                  => env('SUPABASE_SECRET'),
            'region'                  => env('SUPABASE_REGION', 'ap-southeast-1'),
            'bucket'                  => env('SUPABASE_PRIVATE_BUCKET'),
            'endpoint'                => env('SUPABASE_ENDPOINT'),
            'url'                     => env('SUPABASE_URL'),
            'use_path_style_endpoint' => true,

            // Requires a private bucket; public-bucket ACLs cannot protect files.
            'visibility'              => 'private',
            'throw'                   => true,
            'report'                  => false,

            /*
             | SSL verification for the HTTPS calls to Supabase.
             |
             | Many local PHP installs (especially on Windows) ship without a
             | cURL CA bundle, which makes every upload fail with
             | "cURL error 60: unable to get local issuer certificate" and
             | silently fall back to local storage. Pointing the AWS client at
             | a CA bundle committed to the repo makes uploads work on every
             | machine without editing php.ini.
             |
             | Set SUPABASE_CA_BUNDLE in .env to override (e.g. on a server
             | that already has a system CA bundle, set it to `true`).
             */
            'http' => [
                'verify' => env('SUPABASE_CA_BUNDLE', base_path('certs/cacert.pem')),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
