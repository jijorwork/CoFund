<?php

namespace App\Console\Commands;

use App\Domain\Backups\Services\VerifyEncryptedBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cofund:backup:verify {path}')]
#[Description('Verify an encrypted backup archive and its checksums')]
class VerifyBackup extends Command
{
    public function handle(VerifyEncryptedBackup $backups): int
    {
        $manifest = $backups->handle((string) $this->argument('path'));
        $this->info('Backup verified: '.$manifest['created_at'].' ('.count($manifest['files']).' files).');

        return self::SUCCESS;
    }
}
