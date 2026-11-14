# Getting started

Maintained by [coding1688 / Moonsea](https://moonsea.dev). MIT licensed.

Install with Composer. If the package is not available from your configured registry, add its GitHub VCS repository to your application's composer.json first. The existing package name and PHP namespace are unchanged. Laravel discovers the service provider automatically.

Configuration precedence is: package defaults < legacy `pl.files` < `files`. New applications should use only `config/files.php`. Old `pl.*` values are accepted at provider registration time; runtime overrides must use the canonical key. Maps merge and lists replace (including an empty list). After changing cached configuration, rebuild your application's configuration cache.

## Basic upload

```bash
composer require coding1688/pl-files
php artisan vendor:publish --tag=files-config
php artisan migrate
php artisan storage:link
```

The package uses the host Laravel filesystem disks. Local/public upload does not require cloud credentials or a queue worker. Database migrations are needed for the package metadata/settings layer.

```php
$request->validate(['file' => ['required', 'file', 'max:2048']]);
$stored = app(\Coding1688\Files\FileStorage::class)
    ->store($request->file('file'), 'public', 'uploads');
```

`FileStorage::store()` writes bytes and returns `StoredFile` metadata. It does not authorize the request, validate your business MIME policy, or create a File model. The host must validate input, choose permitted disks/directories, persist the metadata it needs, and compensate for failed database writes by removing unused stored bytes.

## References and deletion

Opt into `HasFileReferences` only for business models requiring reference tracking. Configure the referenced fields in that model. Business cover-file foreign keys belong in host migrations.

Deleting a File model is blocked while references exist, but deleting a database row is not a multi-disk physical-delete workflow. The host must coordinate authorization, reference checks, replica cleanup, object deletion and database changes. Never expose raw `FileStorage::delete()` directly to arbitrary user-supplied disk/path values.

## Advanced replication

```bash
php artisan files:sync public archive --now
# Or omit --now and run a worker listening on the configured file-sync queue.
```

Only registered File rows are replicated. Configure the target disk/provider first. Queue connection defaults to the host; override with `FILES_SYNC_CONNECTION`. A sync queue driver runs inline. Multi-worker replication requires a shared atomic-lock cache, such as Redis. Keep storage/worker timeouts below the 600-second task lease; queue retry timing must exceed the 180-second job timeout.

## Custom models and connections

Model overrides must extend their corresponding package models. File, reference and owner models involved in reference updates must share one connection. Replication task/file/replica models must share one connection. Operations use that model connection; cross-connection workflows are rejected rather than claiming distributed transaction safety. Configure custom-connection schema migrations in the host explicitly; package migrations use the normal migration connection.

Publish migrations only when you intend to own them, and then set `files.load_migrations=false` to avoid duplicate loading. Test upgrades and rollback on a copy of your database.

## Optional management API

The package now includes file and storage-account Controllers, Services, Requests and Resources. Routes remain disabled by default. To use the bundled JSON API:

```php
// config/files.php
'management' => [
    'enabled' => true,
    'prefix' => 'files-management',
    'middleware' => ['web', 'auth'],
    'guard' => null,
    'gate' => 'manage-files',
    'locales' => ['en', 'zh_CN', 'zh_TW', 'ja', 'ko'],
],
```

Define the Gate in your application's authorization provider. For example, delegate to a policy/permission check your application owns:

```php
Gate::define('manage-files', fn ($user) => $user->can('manage-storage'));
```

Authentication and this Gate are both required, including if you override the middleware list. The default web middleware retains CSRF protection. Token applications should configure their authenticated API middleware and guard. Keep the API disabled if your host already exposes equivalent routes. Rebuild route caches after enabling or changing the prefix.

| Area | GET | POST |
| --- | --- | --- |
| item | list, options, detail/{id} | upload, delete/{id} |
| storage | list, settings, tasks | create, update/{id}, delete/{id}, save-settings, sync, retry/{id} |

Paths are relative to the configured prefix. Route names start with `files.management.`. Requests should send `Accept: application/json`; uploads use multipart `files[]`. Responses use `data`; file pagination also includes `total` and `last_page`. File upload/storage creation return 201 and sync/retry return 202. Account resources expose credential-presence flags, never credential values. This API grants global storage-administration access; tenant isolation must be implemented by a host adapter, not inferred from the authenticated user ID.

For your own controllers, inject `Coding1688\Files\Management\FileService` or `FileStorageAccountService`, and reuse `Http\Requests` and `Http\Resources`. Service inputs must already be validated. Direct service calls are trusted application operations: your application is responsible for authorization and tenant scoping. The `store()` method intentionally supports already-authorized business upload flows.

Bind a subclass of `Management\ManagementContext` to integrate actor IDs, authorization and transactional audit. The default uses the configured auth guard and emits `files.management.audit` with action, subject, changes and actor ID. A custom context can preserve a host's existing audit tables and permission checks. Do not put credentials into audit metadata.

The bundled services now coordinate metadata creation and cleanup for uploads, and reference checks plus original/replica deletion for managed files. Multi-disk deletion cannot be atomic with a SQL transaction: if a remote deletion fails, the database operation fails but previously removed objects cannot be restored. Applications needing durable recovery should use an explicit deletion-job workflow.
