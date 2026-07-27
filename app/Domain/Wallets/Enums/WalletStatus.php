<?php

namespace App\Domain\Wallets\Enums;

enum WalletStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
