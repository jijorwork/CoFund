<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('cofund:backup:prune')]
#[Description('Remove expired verified backup archives while retaining at least seven')]
class PruneBackups extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk((string) config('cofund.backups.disk'));
        $days = (int) config('cofund.backups.retention_days');
        if ($days < 30) {
            throw new RuntimeException('Backup retention must be at least 30 days.');
        }
        $archives = collect($disk->files('backups'))
            ->filter(fn (string $path): bool => (bool) preg_match('/\Abackups\/[a-zA-Z0-9._-]+\.zip\z/', $path))
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->values();
        $expired = $archives->slice(7)
            ->filter(fn (string $path): bool => $disk->lastModified($path) < now()->subDays($days)->timestamp)
            ->all();
        foreach ($expired as $path) {
            if (! $disk->delete($path)) {
                throw new RuntimeException('An expired backup could not be deleted.');
            }
        }
        $this->info('Expired backups removed: '.count($expired));

        return self::SUCCESS;
    }
}
