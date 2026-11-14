<?php

return [
    'storage_not_ready' => '請完整設定儲存桶、端點及憑證後再啟用。',
    'immutable_disk' => '儲存識別碼及供應商不可修改。',
    'reserved_disk' => '此識別碼保留給內建儲存範本。',
    'storage_in_use' => '此儲存已有檔案或同步工作，請新增儲存帳號並同步，不要直接更改原位置。',
    'sync_busy' => '相關儲存有同步工作進行中，或目前工作不可重試。',
    'default_storage' => '請先選擇其他預設儲存，再停用此儲存。',
    'directory_required' => '請填寫相對於 storage/app 的目錄。',
    'overlapping_directory' => '不同儲存的本機目錄不能相同或互相包含。',
    'duplicate_bucket' => '此儲存桶及端點已被其他儲存帳號使用。',
    'public_url_required' => '公開儲存必須設定公開存取網址。',
    'legacy_url_references' => '此儲存仍有保存完整圖片網址的引用（如文章內文或標誌），請先將這些引用改為檔案 ID，再修改網域。',
    'visibility_mismatch' => '來源檔案與目標儲存的公開性必須一致，私有檔案不可變成公開檔案。',
];
