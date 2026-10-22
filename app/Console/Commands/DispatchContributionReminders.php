<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Actions\DispatchContributionReminders as DispatchReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:contribution-reminders')]
#[Description('Dispatch due and overdue contribution reminders')]
class DispatchContributionReminders extends Command
{
    public function handle(DispatchReminders $reminders): int
    {
        $this->info($reminders->handle().' contribution reminders created.');

        return self::SUCCESS;
    }
}
