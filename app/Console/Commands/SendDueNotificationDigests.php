<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Actions\SendDueNotificationDigests as SendDigests;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-digests')]
#[Description('Collect and send due notification email digests')]
class SendDueNotificationDigests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SendDigests $digests): int
    {
        $this->info($digests->handle().' notification digests sent.');

        return self::SUCCESS;
    }
}
