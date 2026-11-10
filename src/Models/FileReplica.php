<?php

namespace Coding1688\Files\Models;

use Illuminate\Database\Eloquent\Model;

class FileReplica extends Model
{
    protected $fillable = ['file_id', 'disk', 'path', 'checksum'];

    public function getTable(): string
    {
        return (string) config('files.tables.replicas', 'file_replicas');
    }
}
