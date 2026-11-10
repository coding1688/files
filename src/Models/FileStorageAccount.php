<?php

namespace Coding1688\Files\Models;

use Coding1688\Files\StorageRegistry;
use Illuminate\Database\Eloquent\Model;

class FileStorageAccount extends Model
{
    protected $fillable = ['disk', 'name_i18n', 'provider', 'visibility', 'enabled', 'configuration', 'credentials'];

    protected $hidden = ['credentials'];

    public function getTable(): string
    {
        return (string) config('files.tables.storage_accounts', 'file_storages');
    }

    protected static function booted(): void
    {
        $forget = function (self $account): void {
            $registry = app(StorageRegistry::class);
            $registry->forgetDisk($account->disk);
            if ($account->getOriginal('disk')) {
                $registry->forgetDisk($account->getOriginal('disk'));
            }
        };
        static::saved($forget);
        static::deleted($forget);
    }

    protected function casts(): array
    {
        return ['name_i18n' => 'array', 'enabled' => 'boolean', 'configuration' => 'array', 'credentials' => 'encrypted:array'];
    }
}
