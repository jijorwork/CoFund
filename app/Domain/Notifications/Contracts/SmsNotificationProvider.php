<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\ValueObjects\ProviderDeliveryResult;
use App\Domain\Notifications\ValueObjects\SmsMessage;

interface SmsNotificationProvider
{
    public function send(SmsMessage $message): ProviderDeliveryResult;
}
