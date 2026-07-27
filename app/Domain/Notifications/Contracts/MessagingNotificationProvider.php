<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\ValueObjects\MessagingMessage;
use App\Domain\Notifications\ValueObjects\ProviderDeliveryResult;

interface MessagingNotificationProvider
{
    public function send(MessagingMessage $message): ProviderDeliveryResult;
}
