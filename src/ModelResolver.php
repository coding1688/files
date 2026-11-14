<?php

namespace Coding1688\Files;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ModelResolver
{
    /** @return class-string */
    public static function resolve(string $key): string
    {
        $defaults = [
            'file' => Models\File::class,
            'reference' => Models\FileReference::class,
            'replica' => Models\FileReplica::class,
            'storage_account' => Models\FileStorageAccount::class,
            'configuration' => Models\FileConfiguration::class,
            'sync_task' => Models\FileSyncTask::class,
        ];
        $base = $defaults[$key] ?? throw new InvalidArgumentException("Unknown file model: {$key}");
        $class = config("files.models.{$key}", $base);
        if (! is_string($class) || ! is_a($class, $base, true)) {
            throw new InvalidArgumentException("File model {$key} must extend {$base}");
        }

        return $class;
    }

    /** @param list<string> $keys */
    public static function connection(array $keys, ?Model $owner = null): Connection
    {
        $connection = $owner?->getConnection();
        foreach ($keys as $key) {
            $candidate = (new (self::resolve($key)))->getConnection();
            if ($connection !== null && $candidate !== $connection) {
                throw new InvalidArgumentException('File operations require all participating models to share one database connection.');
            }
            $connection = $candidate;
        }

        return $connection ?? throw new InvalidArgumentException('At least one file model is required.');
    }
}
