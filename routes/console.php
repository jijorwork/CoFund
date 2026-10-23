<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:send-digests')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:contribution-reminders')->hourly()->withoutOverlapping();

if (config('cofund.backups.scheduled')
    && config('cofund.backups.disk') === 'cofund-backups'
    && filled(config('filesystems.disks.cofund-backups.bucket'))
    && strlen((string) config('cofund.backups.password')) >= 32) {
    Schedule::command('cofund:backup:create')->dailyAt('02:00')->timezone('Africa/Accra')->withoutOverlapping(180);
    Schedule::command('cofund:backup:verify-latest')->weeklyOn(0, '04:00')->timezone('Africa/Accra')->withoutOverlapping(180);
    Schedule::command('cofund:backup:prune')->dailyAt('05:00')->timezone('Africa/Accra')->withoutOverlapping();
}
