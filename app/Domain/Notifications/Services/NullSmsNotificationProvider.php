<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\SmsNotificationProvider;
use App\Domain\Notifications\ValueObjects\ProviderDeliveryResult;
use App\Domain\Notifications\ValueObjects\SmsMessage;

class NullSmsNotificationProvider implements SmsNotificationProvider
{
    public function send(SmsMessage $message): ProviderDeliveryResult
    {
        return ProviderDeliveryResult::unavailable('sms');
    }
}
