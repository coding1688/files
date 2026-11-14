<?php

use Coding1688\Files\Models\File;
use Coding1688\Files\Models\FileConfiguration;
use Coding1688\Files\Models\FileReference;
use Coding1688\Files\Models\FileReplica;
use Coding1688\Files\Models\FileStorageAccount;
use Coding1688\Files\Models\FileSyncTask;

return [
    'management' => [
        'enabled' => false,
        'prefix' => 'files-management',
        'middleware' => ['web', 'auth'],
        'guard' => null,
        'gate' => 'manage-files',
        'locales' => ['en', 'zh_CN', 'zh_TW', 'ja', 'ko'],
    ],
    /*
    | Model classes are resolved from configuration so host applications can
    | extend or replace them without forking the package.
    */
    /*
    | Load the package migrations automatically. Set to false after
    | publishing them into the host application.
    */
    'load_migrations' => (bool) env('FILES_LOAD_MIGRATIONS', true),

    'models' => [
        'file' => File::class,
        'reference' => FileReference::class,
        'replica' => FileReplica::class,
        'storage_account' => FileStorageAccount::class,
        'configuration' => FileConfiguration::class,
        'sync_task' => FileSyncTask::class,
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
    | Host table holding users. Used for created_by foreign keys; leave null
    | when the host has no users table.
    */
    'user_table' => null,

    /*
    | Legacy integration only. New integrations must own these foreign keys
    | in host migrations. Used only to remove legacy foreign keys on rollback.
    */
    'owner_tables' => [],

    /*
    | Queues used by replication jobs. The connection must exist in the host
    | application's queue configuration.
    */
    'sync' => [
        'connection' => env('FILES_SYNC_CONNECTION'),
        'queue' => env('FILES_SYNC_QUEUE', 'file-sync'),
    ],

    /*
    | Eloquent models whose image columns should be tracked as file references.
    | Empty by default; each host registers its own consumers.
    */
    'reference_models' => [],

    /*
    | Disk that serves public URLs when a verified replica exists on it.
    | Leave null to always serve from the file's own disk.
    */
    'preferred_disk' => null,

    /*
    | Ordered list of disks used for public URLs; overrides preferred_disk.
    | The database value (file_configurations.preferred_disks) wins over this.
    */
    'preferred_disks' => null,

    /*
    | Providers that require an endpoint and do not accept S3 ACL headers.
    */
    'endpoint_required_providers' => ['cloudflare', 'tencent'],
    'strip_acl_providers' => ['cloudflare', 'tencent'],

    /*
    | Class of the enum cast for files.type. Null uses the package enum.
    */
    'type_enum' => null,

    /*
    | Selectable type values. Null exposes every case of the type enum.
    */
    'types' => null,

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
            'cos' => ['provider' => 'tencent', 'driver' => 's3', 'visibility' => 'public'],
        ],
    ],
];
