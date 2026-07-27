<?php

namespace App\Domain\Notifications\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case Sms = 'sms';
    case Messaging = 'messaging';
}
