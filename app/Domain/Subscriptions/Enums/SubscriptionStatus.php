<?php

namespace App\Domain\Subscriptions\Enums;

enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case GracePeriod = 'grace_period';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function grantsAccess(): bool
    {
        return $this === self::Active || $this === self::GracePeriod;
    }
}
