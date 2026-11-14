<?php

namespace Coding1688\Files\Console\Commands;

use Coding1688\Files\ModelResolver;
use Coding1688\Files\StorageRegistry;
use Illuminate\Console\Command;

class DisksCommand extends Command
{
    protected $signature = 'files:disks';

    protected $description = 'List configured storage disks with file and replica counts';

    public function handle(StorageRegistry $registry): int
    {
        $rows = [];
        foreach ($registry->providers() as $disk => $provider) {
            $rows[] = [
                $disk,
                $provider['provider'],
                $provider['configured'] ? 'yes' : 'no',
                $provider['enabled'] ? 'yes' : 'no',
                implode('|', $provider['visibilities']),
                (int) (ModelResolver::resolve('file'))::where('disk', $disk)->count(),
                (int) (ModelResolver::resolve('replica'))::where('disk', $disk)->count(),
            ];
        }

        $this->table(['disk', 'provider', 'configured', 'enabled', 'visibility', 'files', 'replicas'], $rows);

        $preferred = $registry->preferredDisks();
        $this->line('preferred order: '.($preferred === [] ? '(none)' : implode(' -> ', $preferred)));

        return self::SUCCESS;
    }
}
