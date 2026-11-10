<?php

namespace Coding1688\Files\Console\Commands;

use Coding1688\Files\Models\FileConfiguration;
use Coding1688\Files\Models\FileReplica;
use Coding1688\Files\StorageRegistry;
use Illuminate\Console\Command;

class FailoverCommand extends Command
{
    protected $signature = 'files:failover {disks?* : Ordered disk preference, first one wins}';

    protected $description = 'Show or change the preferred storage order used for public file URLs';

    public function handle(StorageRegistry $registry): int
    {
        $disks = array_values(array_filter(array_map('strval', (array) $this->argument('disks')), fn (string $disk): bool => $disk !== ''));
        $providers = $registry->providers();

        if ($disks === []) {
            $this->table(
                ['disk', 'provider', 'enabled', 'replicas'],
                collect($providers)->map(fn (array $provider, string $disk): array => [
                    $disk, $provider['provider'], $provider['enabled'] ? 'yes' : 'no', (int) FileReplica::where('disk', $disk)->count(),
                ])->values()->all(),
            );
            $this->line('preferred order: '.(($current = $registry->preferredDisks()) === [] ? '(none)' : implode(' -> ', $current)));

            return self::SUCCESS;
        }

        foreach ($disks as $disk) {
            if (! isset($providers[$disk])) {
                $this->error("Unknown disk: {$disk}");

                return self::FAILURE;
            }
            if (FileReplica::where('disk', $disk)->doesntExist()) {
                $this->warn("No replicas recorded on {$disk} yet; files will fall back to their own disk.");
            }
        }

        $configuration = FileConfiguration::find(1);

        if ($configuration === null) {
            $uploads = (array) config('files.uploads', []);
            $configuration = FileConfiguration::create([
                'id' => 1,
                'default_disk' => $uploads['default_disk'] ?? null,
                'max_upload_mb' => (int) ($uploads['max_upload_mb'] ?? 32),
                'allowed_extensions' => (array) ($uploads['allowed_extensions'] ?? []),
            ]);
        }

        $configuration->preferred_disks = $disks;
        $configuration->save();
        $registry->flush();

        $this->info('preferred order: '.implode(' -> ', $disks));

        return self::SUCCESS;
    }
}
