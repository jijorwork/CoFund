<?php

namespace App\Console\Commands;

use App\Domain\Backups\Services\CreateEncryptedBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cofund:backup:create')]
#[Description('Create and verify an encrypted full backup')]
class CreateBackup extends Command
{
    public function handle(CreateEncryptedBackup $backups): int
    {
        $this->info('Encrypted backup created: '.$backups->handle());

        return self::SUCCESS;
    }
}
