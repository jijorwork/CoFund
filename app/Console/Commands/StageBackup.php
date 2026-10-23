<?php

namespace App\Console\Commands;

use App\Domain\Backups\Services\StageEncryptedBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cofund:backup:stage {path} {target}')]
#[Description('Verify and stage a backup in a new directory for recovery')]
class StageBackup extends Command
{
    public function handle(StageEncryptedBackup $backups): int
    {
        $target = $backups->handle((string) $this->argument('path'), (string) $this->argument('target'));
        $this->info('Backup staged at: '.$target);

        return self::SUCCESS;
    }
}
