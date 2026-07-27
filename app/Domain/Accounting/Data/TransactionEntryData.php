<?php

namespace App\Domain\Accounting\Data;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Models\Wallet;

final readonly class TransactionEntryData
{
    public function __construct(
        public LedgerAccount $ledgerAccount,
        public EntryType $entryType,
        public int $amountMinor,
        public ?string $description = null,
        public ?Member $member = null,
        public ?Wallet $wallet = null,
    ) {}
}
