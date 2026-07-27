<?php

namespace App\Jobs;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\DeliverNotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverEmailNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function __construct(public string $deliveryUuid) {}

    public function handle(DeliverNotificationChannel $delivery): void
    {
        $delivery->handle($this->deliveryUuid, NotificationChannel::Email);
    }
}
