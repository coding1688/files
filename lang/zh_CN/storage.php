<?php

return [
    'storage_not_ready' => '请配齐存储桶、端点和凭据后再启用存储。',
    'immutable_disk' => '存储标识和提供商不可修改。',
    'reserved_disk' => '此标识已保留给内置存储模板。',
    'storage_in_use' => '该存储已有文件或同步任务，请新增存储账号并同步，不要直接更改原位置。',
    'sync_busy' => '相关存储有同步任务进行中，或当前任务不可重试。',
    'default_storage' => '请先选择其它默认存储，再停用此存储。',
    'directory_required' => '请填写相对于 storage/app 的目录。',
    'overlapping_directory' => '不同存储的本地目录不能相同或互相包含。',
    'duplicate_bucket' => '此存储桶和端点已被其它存储账号使用。',
    'public_url_required' => '公开存储必须配置公开访问地址。',
    'legacy_url_references' => '该存储仍有保存完整图片地址的引用（如文章正文或标志），请先将这些引用改为文件 ID，再修改域名。',
    'visibility_mismatch' => '源文件与目标存储的公开性必须一致，私有文件不可变为公开文件。',
];
