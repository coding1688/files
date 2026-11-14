<?php

namespace Coding1688\Files\Models;

use Coding1688\Files\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FileReference extends Model
{
    protected $fillable = ['file_id', 'reference_type', 'reference_id', 'field'];

    public function getTable(): string
    {
        return (string) config('files.tables.references', 'file_references');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::resolve('file'), 'file_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
