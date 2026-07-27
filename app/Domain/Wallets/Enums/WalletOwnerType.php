<?php

namespace App\Domain\Wallets\Enums;

enum WalletOwnerType: string
{
    case Member = 'member';
    case Branch = 'branch';
    case Organisation = 'organisation';
    case CollectorUser = 'collector_user';
    case CustodianUser = 'custodian_user';
}
