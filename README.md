# coding1688/pl-files

[![CI](https://github.com/coding1688/pl-files/actions/workflows/ci.yml/badge.svg)](https://github.com/coding1688/pl-files/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)

Laravel 文件模块：上传、文件引用追踪、多磁盘存储、副本复制与多云故障切换。

- 支持 **PHP ^8.2**，**Laravel ^11 / ^12 / ^13**
- 不依赖任何业务模型：模型、表名、队列、文件类型全部通过配置注入
- 内置存储 provider：本地、AWS S3、Cloudflare R2、腾讯云 COS、阿里云 OSS、GCS、任意 S3 兼容存储
- 一个应用可同时挂多个云，文件可在云之间复制，并在故障时切换出图域名

## 目录

- [安装](#安装)
- [宿主配置](#宿主配置)
- [磁盘与凭据](#磁盘与凭据)
- [快速上手](#快速上手)
- [图片链接与引用](#图片链接与引用)
- [多云互备与迁徙](#多云互备与迁徙)
- [配置项参考](#配置项参考)
- [扩展点](#扩展点)
- [命令](#命令)
- [测试](#测试)
- [排错](#排错)
- [版本与升级](#版本与升级)

## 配置

宿主可以用两种方式覆盖默认值（后者优先）：

- `config/files.php`（`php artisan vendor:publish --tag=files-config`）
- `config/pl.php` 的 `files` 键（与 `pl-devtools`、`pl-fields` 统一）

```php
// config/pl.php
return [
    'files' => [
        'uploads' => ['default_disk' => 'cos'],
    ],
];
```

## 安装

### 1. 引入仓库

仓库是公开的，用 HTTPS 即可，不需要 token 也不需要 SSH：

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/coding1688/pl-files.git" }
    ],
    "require": {
        "coding1688/pl-files": "^0.6"
    }
}
```

如果已发布到 Packagist（见文末），可以省略 `repositories`，直接 `composer require coding1688/pl-files`。

要给包提 PR 时再用 SSH：

```json
{ "type": "vcs", "url": "git@github.com:coding1688/pl-files.git" }
```

本地开发（单仓 path，改代码立即生效）：

```json
{
    "repositories": [
        { "type": "path", "url": "packages/coding1688/pl-files", "options": { "symlink": true } }
    ]
}
```

> `0.x` 版本的 caret 会锁次版本：包发 `0.5.0` 时，宿主约束要同步改成 `^0.5`。到 `1.0` 之后 `^1.0` 才能自动接受 `1.x`。

### 2. 安装

```bash
composer update coding1688/pl-files
```

包通过 Laravel 自动发现注册 `Coding1688\Files\FilesServiceProvider`；如果宿主关闭了自动发现，手动加到 `bootstrap/providers.php`。

### 3. 迁移

```bash
php artisan migrate
```

包内迁移会自动加载（`files.load_migrations`，默认 `true`）。迁移会创建 `files`、`file_references`、`file_replicas`、`file_storages`、`file_configurations`、`file_sync_tasks` 六张表。

外键只在宿主表存在时建立：

- `files.user_table`：`created_by` 指向的用户表（不填则不建外键）
- `files.owner_tables`：带 `cover_file_id` 列的业务表

如果想修改表名或接管迁移：

```bash
php artisan vendor:publish --tag=files-migrations
# 然后设置 FILES_LOAD_MIGRATIONS=false，避免包内迁移重复加载
```

### 4. 发布配置（可选）

包的默认配置在 `config/files.php` 里，宿主只需要写差异：

```bash
php artisan vendor:publish --tag=files-config
```

配置合并规则：映射递归合并、列表整体替换。所以宿主只写 `uploads.max_upload_mb` 不会丢掉其它上传默认值。

### 5. 队列

复制任务默认走 `file-sync` 连接：

```bash
php artisan queue:work --queue=file-sync
```

连接名/队列名可以改：`files.sync.connection`、`files.sync.queue`。

## 宿主配置

最小可用的 `config/files.php`：

```php
<?php

use App\Models\Admin\Product;
use App\Models\Common\FileStorageAccount;

return [
    // 让包内的 StorageRegistry 使用宿主加了 i18n 的账号模型
    'models' => [
        'storage_account' => FileStorageAccount::class,
    ],

    // 迁移建立外键时使用
    'user_table' => 'users',
    'owner_tables' => ['products', 'articles'],

    // 宿主的文件引用消费者（供宿主自己的重建命令使用）
    'reference_models' => [Product::class],

    // 只覆盖需要改的项
    'uploads' => [
        'default_disk' => env('UPLOAD_DISK', 'public'),
        'max_upload_mb' => 32,
    ],
];
```

包的模型是基类。宿主需要工厂、额外关系或本地化名称时继承即可：

```php
namespace App\Models\Common;

use Coding1688\Files\Models\File as BaseFile;

class File extends BaseFile
{
    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
```

## 磁盘与凭据

包本身不定义磁盘，磁盘来自宿主的 `config/filesystems.php`。两种接入方式：

**A. 只用配置（最小接入）**

在 `files.uploads.providers` 登记磁盘，并加入 `enabled_disks`：

```php
'uploads' => [
    'providers' => [
        'public' => ['provider' => 'local', 'driver' => 'local', 'visibility' => 'public'],
        'cos' => ['provider' => 'tencent', 'driver' => 's3', 'visibility' => 'public'],
    ],
    'enabled_disks' => ['public', 'cos'],
],
```

**B. 多账号 + 后台管理**

把账号写进 `file_storages`（凭据用 `encrypted:array` 加密存储），包会在运行时优先使用数据库配置。多账号适合"同一 provider 多个桶/租户"。

| provider | 必填字段 | 说明 |
| --- | --- | --- |
| `local` / `local_private` | `directory` | 相对 `storage/app` |
| `aws` / `google` / `aliyun` / `s3_compatible` | `bucket`、`region`、`key`、`secret` | 可选 `endpoint`、`url` |
| `cloudflare`（R2） / `tencent`（COS） | 同上 + **`endpoint`** | 会剥离 S3 ACL 头，见 `files.strip_acl_providers` |

S3 系驱动需要宿主的 AWS SDK 依赖：

```bash
composer require league/flysystem-aws-s3-v3
```

### 腾讯云 COS 示例

```dotenv
COS_ACCESS_KEY_ID=AKID...
COS_SECRET_ACCESS_KEY=...
COS_REGION=ap-singapore
COS_BUCKET=example-bucket-1250000000
COS_ENDPOINT=https://cos.ap-singapore.myqcloud.com
COS_URL=https://img.example.com
COS_USE_PATH_STYLE_ENDPOINT=true
COS_UPLOAD_VISIBILITY=public
```

- 桶名带 APPID；`region` 必须与桶一致
- `COS_URL` 不配会得到不可公开访问的 endpoint 直链，需要桶公共读或绑定 CDN
- 证书类文件用 `private`，配合 `temporaryUrl()` 下发

## 快速上手

### 上传文件并登记

```php
use Coding1688\Files\FileStorage;
use Coding1688\Files\UploadConfiguration;

final class UploadController
{
    public function __construct(
        private readonly FileStorage $storage,
        private readonly UploadConfiguration $uploads,
    ) {}

    public function store(Request $request)
    {
        $request->validate(['file' => $this->uploads->fileRules()]);

        $disk = $this->uploads->publicDisk();
        $stored = $this->storage->store($request->file('file'), $disk, 'uploads/product');

        return File::create($stored->toArray() + [
            'disk' => $disk,
            'type' => 'product_cover',
            'visibility' => 'public',
            'created_by' => $request->user()->id,
        ]);
    }
}
```

`store()` 返回 `StoredFile`：路径、原始名、扩展名、MIME、大小、SHA-256、宽高。写入失败时不会留下半条记录，调用方负责把文件行放进同一个事务。

### 读取 URL

```php
$file->publicUrl();                                    // 公开文件；私有返回 null
$file->temporaryUrl(now()->addMinutes(10));            // 预签名；驱动不支持时返回 null
$file->replicaOn('cos');                               // 指定盘上的副本
```

`publicUrl()` 会按 `preferred_disks` 顺序找第一个有副本的盘，找不到就回退到文件自身磁盘。

### 表单校验

```php
$rules = [
    'cover' => $uploads->fileRules(),                    // 扩展名 + 体积
    'gallery.*' => $uploads->fileRules(['jpg', 'png']),  // 只允许图片
];
```

`fileRules()` 返回 `required|file|max:<KB>|mimes:<ext...>`；当上传被禁用（体积 0 或没有允许的扩展名）时返回 `prohibited`。

## 图片链接与引用

### 绑定封面

```php
use Coding1688\Files\Concerns\HasImageCover;

class Article extends Model
{
    use HasImageCover;
}
```

建两列即可：`cover_file_id`（外键）和 `external_cover_url`（外链兜底）。trait 会补一个 `cover_url` 计算属性——赋值时自动在"文件 ID"和"外部 URL"之间归一化，读取时按文件解析成 URL。

### 追踪引用

```php
use Coding1688\Files\Concerns\HasFileReferences;

class Product extends Model
{
    use HasFileReferences;

    /** 需要追踪的列 */
    public function fileReferenceFields(): array
    {
        return ['cover_file_id', 'gallery_images'];
    }

    /** 列 => 返回文件 ID 列表的访问器（数组列用这个） */
    public function fileReferenceIdFields(): array
    {
        return ['cover_file_id' => 'cover_file_id', 'gallery_images' => 'gallery_file_ids'];
    }
}
```

- 保存前校验：不存在或非法的文件 ID 会抛 `ValidationException`，错误键是对应字段名，**不会写入半条记录**。
- 保存后同步 `file_references`；删除模型时清理引用。
- 文件被引用时删除会抛异常（`File::deleting` 守卫 + 数据库外键双保险）。
- 多步写操作请在宿主侧包事务（trait 不再接管 `save()`）。

### 展示图片

```php
use Coding1688\Files\ImageLinks;

app(ImageLinks::class)->presentation($product->gallery_images);
// [['file_id' => 1, 'url' => 'https://...'], ['external_url' => '...', 'url' => '...']]
```

`ImageLinks` 按请求缓存已解析的文件行；同一请求里文件被改/删后需要 `forget($id)` 或 `flush()`。

`cover()` / `gallery()` 负责把 URL 输入归一化成 `file_id`，`validatePublicImages()` 校验"公开且是图片"。

## 多云互备与迁徙

同一个应用可同时挂多个云，互不影响。

```bash
php artisan files:disks                       # 每个盘的 provider / 是否可用 / 文件与副本数量
php artisan files:smoke cos                   # 上传、读回、签名、删除，验证一个盘
php artisan files:sync public cos --now       # 把 public 的已登记文件复制到 COS（校验和、幂等、可重跑）
php artisan files:failover cos r2             # 公共 URL 优先走 COS，副本缺失时回退 R2/原盘
php artisan files:failover                    # 查看当前顺序
```

- `files:sync` 不带 `--now` 时按 `files.sync.*` 排队，由 worker 逐文件处理；中断后重新执行会跳过已校验过的副本。
- `files:failover` 把顺序写入 `file_configurations.preferred_disks`，**不需要发版或清配置缓存**，下一次请求即生效。
- 不删除源文件；复制只新增 `file_replicas`。
- 一个云不可用时的标准动作：`files:sync 老云 新云` 补齐副本 → `files:smoke 新云` → `files:failover 新云` → 观察 → 再决定是否下线老云。
- 回滚：再执行一次 `files:failover 老云`。

## 配置项参考

| 键 | 默认 | 说明 |
| --- | --- | --- |
| `files.load_migrations` | `true` | 是否自动加载包内迁移 |
| `files.models.file` 等 | 包内模型 | 模型类解析；`storage_account` 可指向宿主子类 |
| `files.tables.*` | `files` / `file_references` / `file_replicas` / `file_storages` / `file_configurations` / `file_sync_tasks` | 表名映射 |
| `files.user_table` | `null` | `created_by` 外键指向的用户表 |
| `files.owner_tables` | `[]` | 迁移时补 `cover_file_id` 外键的业务表 |
| `files.type_enum` | `null` | `files.type` 的枚举类；null 用包内 `FileType` |
| `files.types` | `null` | 可选类型白名单；null 暴露枚举全部值 |
| `files.uploads.default_disk` | `public` | 新上传默认盘 |
| `files.uploads.enabled_disks` | `['public', 'local']` | 允许使用的盘 |
| `files.uploads.max_upload_mb` | `32` | 上传体积上限 |
| `files.uploads.limits.hard_max_upload_mb` | `1024` | 配置无法突破的硬上限 |
| `files.uploads.allowed_extensions` | 见包内配置 | 允许的扩展名 |
| `files.uploads.limits.forbidden_extensions` | 见包内配置 | 始终禁止的扩展名 |
| `files.uploads.providers` | 见包内配置 | 磁盘 => provider/visibility |
| `files.preferred_disks` | `null` | 公共 URL 优先盘顺序（被数据库值覆盖） |
| `files.preferred_disk` | `null` | 单个优先盘（旧字段，兼容用） |
| `files.endpoint_required_providers` | `['cloudflare', 'tencent']` | 必须配置 endpoint 的 provider |
| `files.strip_acl_providers` | `['cloudflare', 'tencent']` | 写入时剥离 S3 ACL 头的 provider |
| `files.sync.connection` / `files.sync.queue` | `file-sync` | 复制任务队列 |
| `files.reference_models` | `[]` | 宿主登记的文件引用消费者（宿主命令使用） |

## pl-* 系列

- pl-files（本包）：上传、引用追踪、多磁盘存储、多云复制
- [pl-fields](https://github.com/coding1688/pl-fields)：字段类型注册与选项来源
- [pl-debug](https://github.com/coding1688/pl-debug)：请求级 SQL 性能分析与工具栏

## 本地化

包内自带 **5 种语言**：`en`、`zh_CN`、`zh_TW`、`ja`、`ko`，语言文件在 `lang/{locale}/file.php`，命名空间为 `files`：

```php
__('files::file.in_use');       // 文件仍被引用
__('files::file.missing');      // 引用的文件不存在
__('files::file.type_product'); // 类型标签（FileType::label() 使用）
```

- 宿主需要改文案：`php artisan vendor:publish --tag=files-lang`，然后编辑 `lang/vendor/files/{locale}/file.php`。发布后的文件优先于包内的。
- 新增语言：在 `lang/vendor/files/{locale}/file.php` 放同名键，Laravel 会按 `fallback_locale` 回退。
- 文件模块没有其它语言的翻译；缺失时统一回退到 `fallback_locale`（通常是 `en`）。

保留这 5 种语言时，宿主侧对应的 UI 文案也需要同步存在：后台文件页面的 `pages/file/**/locales/{locale}.json`、菜单文案 `lang/{locale}/common/file/main.php`、存储校验消息 `lang/{locale}/common/file/storage.php`。

## 扩展点

| 想做的事 | 做法 |
| --- | --- |
| 用宿主自己的模型 | 继承包内模型，把类写进 `files.models.*` |
| 自定义文件类型 | 自己写枚举，设 `files.type_enum`，用 `files.types` 限制可选项 |
| 换表名/表前缀 | 覆盖 `files.tables.*` |
| 支持新的 S3 兼容云 | 在 `files.uploads.providers` 登记 provider，必要时加进 `endpoint_required_providers` / `strip_acl_providers` |
| 换队列 | 覆盖 `files.sync.*` |
| 增加本地化名称 | 在宿主模型里加 `HasLocalizedAttributes` 之类的访问器，包不会强制 |

## 命令

| 命令 | 说明 |
| --- | --- |
| `files:disks` | 列出磁盘、provider、可用性、文件/副本数、当前优先顺序 |
| `files:sync {source} {target} [--now]` | 复制已登记文件；`--now` 在当前进程同步执行 |
| `files:failover [disks...]` | 查看或设置公共 URL 的优先盘顺序 |
| `files:smoke {disk}` | 上传 → 读回 → 签名 → 删除，验证一个盘 |

## 测试

```php
use Illuminate\Support\Facades\Storage;

Storage::fake('public');
Storage::fake('cos', ['url' => 'https://cos.example.test']);
```

需要替换磁盘实现时（例如断言写入目标）：

```php
$this->partialMock(StorageRegistry::class, fn ($mock) => $mock
    ->shouldReceive('disk')->andReturnUsing(fn (string $disk) => Storage::disk($disk)));
```

注意：`StorageRegistry` 会缓存每个请求解析过的账号与优先顺序，测试里直接改 `file_storages`（尤其 `query()->update()` 这类绕过模型事件的批量写）后要调用 `app(StorageRegistry::class)->flush()`。

## 排错

| 现象 | 原因 | 处理 |
| --- | --- | --- |
| 公共 URL 403/404 | 桶未开公共读，或 `url` 没配 CDN 域名 | 开桶公共读或配 `configuration.url` |
| `storage_not_ready` | provider 缺 bucket/key/secret，或 R2/COS 缺 endpoint | 补全配置并启用账号 |
| `visibility_mismatch` | 目标盘可见性与文件不一致（public 文件不能复制到 private 盘） | 调整账号可见性或选其它目标 |
| R2/COS 写入报 ACL 错误 | 云端不接受 S3 ACL 头 | 把 provider 加进 `files.strip_acl_providers` |
| 复制任务一直 pending | `files.sync` 队列没有 worker | 起 `queue:work --queue=file-sync`，或临时用 `--now` |
| `files:failover` 后 URL 没变 | 目标盘没有该文件的副本 | 先 `files:sync`，再 `files:disks` 确认副本数 |
| 保存时报文件无效 | ID 不存在，或图片不是 public/非图片类型 | 错误键即字段名，按字段修正 |
| 私有文件 `publicUrl()` 是 null | 私有文件不生成公开 URL | 用 `temporaryUrl()` |

## 发布到 Packagist（可选）

仓库是公开的，可以直接用 VCS 引入；登记到 Packagist 之后，宿主只要 `composer require coding1688/pl-files`，并且能拿到版本列表与统计数据。

1. 打开 <https://packagist.org/packages/submit>，填入 `https://github.com/coding1688/pl-files`。
2. 在 Packagist 的包设置里连接 GitHub（"Set up GitHub integration"）或配置 Webhook，之后每次 `git push --tags` 会自动同步版本。
3. 之后宿主不需要再声明 `repositories`。

## 版本与升级

- 语义化版本；`0.x` 阶段每个 minor 都可能带破坏性变更，升级前看本文件与 release notes。
- 升级步骤：改宿主 `composer.json` 约束 → `composer update coding1688/pl-files` → `php artisan migrate` → 跑宿主测试。
- 迁移只新增表和列；不删除、不改现有列。

## 状态

- `v0.5.x`：包内自带 5 种语言（en/zh_CN/zh_TW/ja/ko），文案改用 `files::` 命名空间；仓库转为公开。
- `v0.4.x`：COS provider、多云优先顺序、迁移/冒烟/状态命令、签名 URL、优先副本解析。
- `v0.3.0`：去掉 `request()` 依赖与 trait 的 `save()`/`delete()` 覆盖，队列名与类型可配。
- `v0.2.0`：核心类、模型、复制 Job 与迁移迁入。
