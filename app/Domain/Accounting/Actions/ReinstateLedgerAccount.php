<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Models\LedgerAccount;

class ReinstateLedgerAccount
{
    public function __construct(private ActivateLedgerAccount $activate) {}

    public function handle(LedgerAccount $account): LedgerAccount
    {
        return $this->activate->handle($account);
    }
}
