<?php

namespace Coding1688\Files\Models;

use Illuminate\Database\Eloquent\Model;

class FileSyncTask extends Model
{
    protected $fillable = ['source_disk', 'target_disk', 'status', 'last_file_id', 'max_file_id', 'total', 'copied', 'skipped', 'failed', 'errors', 'started_at', 'finished_at', 'created_by'];

    public function getTable(): string
    {
        return (string) config('files.tables.sync_tasks', 'file_sync_tasks');
    }

    protected function casts(): array
    {
        return ['errors' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
