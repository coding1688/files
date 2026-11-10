<?php

return [
    /*
    | Model classes are resolved from configuration so host applications can
    | extend or replace them without forking the package.
    */
    'models' => [
        'file' => Coding1688\Files\Models\File::class,
        'reference' => Coding1688\Files\Models\FileReference::class,
        'replica' => Coding1688\Files\Models\FileReplica::class,
        'storage_account' => Coding1688\Files\Models\FileStorageAccount::class,
        'configuration' => Coding1688\Files\Models\FileConfiguration::class,
        'sync_task' => Coding1688\Files\Models\FileSyncTask::class,
    ],

    /*
    | Table names can be overridden for hosts that use a prefix or a different
    | naming convention. Keep the defaults unless you are integrating with an
    | existing schema.
    */
    'tables' => [
        'files' => 'files',
        'references' => 'file_references',
        'replicas' => 'file_replicas',
        'storage_accounts' => 'file_storages',
        'configuration' => 'file_configurations',
        'sync_tasks' => 'file_sync_tasks',
    ],

    /*
    | Queues used by replication jobs. The connection must exist in the host
    | application's queue configuration.
    */
    'sync' => [
        'connection' => env('FILES_SYNC_CONNECTION', 'file-sync'),
        'queue' => env('FILES_SYNC_QUEUE', 'file-sync'),
    ],

    /*
    | File types. The package only relies on the string value; hosts can add
    | their own entries and translate labels where they render them.
    */
    'types' => [
        'general', 'system', 'product', 'product_cover', 'content_article',
        'content_cover', 'brand_logo', 'store', 'user_avatar', 'carousel',
    ],

    /*
    | Eloquent models whose image columns should be tracked as file references.
    | Empty by default; each host registers its own consumers.
    */
    'reference_models' => [],

    'uploads' => [
        'default_disk' => 'public',
        'enabled_disks' => ['public', 'local'],
        'max_upload_mb' => 32,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg'],
        'limits' => [
            'hard_max_upload_mb' => 1024,
            'forbidden_extensions' => ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'cgi', 'pl', 'sh', 'exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'html', 'htm', 'js'],
        ],
        'providers' => [
            'public' => ['provider' => 'local', 'driver' => 'local', 'visibility' => 'public'],
            'local' => ['provider' => 'local_private', 'driver' => 'local', 'visibility' => 'private'],
            's3' => ['provider' => 'aws', 'driver' => 's3', 'visibility' => 'private'],
            'r2' => ['provider' => 'cloudflare', 'driver' => 's3', 'visibility' => 'private'],
            'oss' => ['provider' => 'aliyun', 'driver' => 's3', 'visibility' => 'private'],
            'gcs' => ['provider' => 'google', 'driver' => 's3', 'visibility' => 'private'],
            's3_compatible' => ['provider' => 's3_compatible', 'driver' => 's3', 'visibility' => 'private'],
        ],
    ],
];
