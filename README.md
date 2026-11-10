# coding1688/files

Laravel 文件模块：上传、文件引用追踪、多磁盘存储与副本复制。设计目标是**可整包复用到其它项目**。

- PHP `^8.2`，Laravel `^11 | ^12 | ^13`
- 不依赖任何业务模型；所有模型/表名/队列名通过 `config/files.php` 注入
- 二进制内容与会话状态无关；不读 `env()`（配置在应用侧完成）

## 边界

进入包：文件模型与迁移、`FileStorage`、`UploadConfiguration`、`StorageRegistry`、`StorageSynchronizer`、`FileReferences`、`ImageLinks`、`HasFileReferences` / `HasImageCover`、复制 Job、`FileType`。

留在应用：后台控制器/请求/资源/服务、菜单与权限、审计日志、业务模型清单（`reference_models`）、前端页面。

## 安装（单仓 path 仓库）

应用根 `composer.json`：

```json
"repositories": [
    { "type": "path", "url": "packages/coding1688/files", "options": { "symlink": true } }
]
```

```bash
composer require coding1688/files:@dev
php artisan vendor:publish --tag=files-config
php artisan migrate
```

## 配置要点

| 键 | 作用 |
| --- | --- |
| `files.models.*` | 模型类（默认包内实现，可替换） |
| `files.tables.*` | 表名映射 |
| `files.sync.connection` / `files.sync.queue` | 复制任务使用的队列 |
| `files.types` | 允许的文件类型值（与 `file` 表 `type` 列一致） |
| `files.uploads.*` | 磁盘、体积、扩展名策略 |
| `files.reference_models` | 需要追踪文件引用的业务模型（默认空） |

## 升级注意

- `files.types` 与历史数据兼容：不要删除已存在的类型值，否则枚举 cast 会失败。
- 表名默认与既有项目一致（`files`、`file_references`、`file_replicas`、`file_storages`、`file_configurations`、`file_sync_tasks`）。
- 包内迁移用于全新安装；已上线的宿主项目不要重复执行，改用宿主现有迁移。

## 包内容

| 命名空间 | 类 |
| --- | --- |
| `Coding1688\Files` | `FileStorage`、`UploadConfiguration`、`FileReferences`、`ImageLinks`、`StorageRegistry`、`StorageSynchronizer`、`StoredFile` |
| `Coding1688\Files\Concerns` | `HasFileReferences`、`HasImageCover` |
| `Coding1688\Files\Constants` | `FileType`（枚举，值需与 `files.type` 列一致） |
| `Coding1688\Files\Jobs` | `FileSyncBatch` |
| `Coding1688\Files\Models` | `File`、`FileReference`、`FileReplica`、`FileStorageAccount`、`FileConfiguration`、`FileSyncTask` |

## 宿主接入约定

- 包内模型是基类。宿主需要工厂、`uploader()` 关系或 i18n 名称时，继承即可（本项目的 `App\Models\Common\File*` 就是这样做的）。
- 包内 `StorageRegistry` 通过 `files.models.storage_account` 解析存储账号模型，因此宿主可以给它加上 `_i18n` 行为而不影响包内部。
- `created_by` 外键只在 `files.user_table` 指向的表存在时才建立；`cover_file_id` 外键只对 `files.owner_tables` 中实际存在的表建立。

## 状态

v0.2.0：核心类、模型、复制 Job 与迁移已迁入；签名 URL / 优先副本解析在 v0.3.0。
