<?php

namespace App\Console\Commands;

use App\Domain\Backups\Services\ExerciseMysqlRestore;
use App\Domain\Backups\Services\StageEncryptedBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('cofund:backup:verify-latest')]
#[Description('Verify the latest backup, staging it or exercising a disposable MySQL restore')]
class VerifyLatestBackup extends Command
{
    public function handle(StageEncryptedBackup $backups, ExerciseMysqlRestore $mysqlRestore, Filesystem $files): int
    {
        $disk = Storage::disk((string) config('cofund.backups.disk'));
        $latest = collect($disk->files('backups'))
            ->filter(fn (string $path): bool => (bool) preg_match('/\Abackups\/[a-zA-Z0-9._-]+\.zip\z/', $path))
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->first();
        if ($latest === null) {
            throw new RuntimeException('No backup archive is available.');
        }
        if (config('cofund.backups.restore_mysql.enabled')) {
            $result = $mysqlRestore->handle($latest);
            $this->info(sprintf('Latest backup restored into disposable MySQL: %d tables and %d migration rows.', $result['tables'], $result['migration_rows']));

            return self::SUCCESS;
        }

        $target = sys_get_temp_dir().'/cofund-recovery-check-'.bin2hex(random_bytes(12));
        try {
            $backups->handle($latest, $target);
            $this->info('Latest backup verified and staged: '.$latest);
        } finally {
            if ($files->isDirectory($target)) {
                $files->deleteDirectory($target);
            }
        }

        return self::SUCCESS;
    }
}
