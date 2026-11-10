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

## 状态

骨架阶段（P1）：`composer.json`、`FilesServiceProvider`、默认配置已就位；核心类与迁移将在 P2 迁入。
