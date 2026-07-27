<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\MessagingNotificationProvider;
use App\Domain\Notifications\ValueObjects\MessagingMessage;
use App\Domain\Notifications\ValueObjects\ProviderDeliveryResult;

class NullMessagingNotificationProvider implements MessagingNotificationProvider
{
    public function send(MessagingMessage $message): ProviderDeliveryResult
    {
        return ProviderDeliveryResult::unavailable('messaging');
    }
}
