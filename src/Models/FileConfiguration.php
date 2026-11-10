<?php

namespace Coding1688\Files\Models;

use Illuminate\Database\Eloquent\Model;

class FileConfiguration extends Model
{
    protected $fillable = ['id', 'default_disk', 'max_upload_mb', 'allowed_extensions'];

    public function getTable(): string
    {
        return (string) config('files.tables.configuration', 'file_configurations');
    }

    protected function casts(): array
    {
        return ['max_upload_mb' => 'integer', 'allowed_extensions' => 'array'];
    }
}
