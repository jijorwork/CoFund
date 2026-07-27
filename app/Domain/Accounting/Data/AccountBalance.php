<?php

namespace App\Domain\Accounting\Data;

use App\Domain\Accounting\Enums\NormalBalance;

final readonly class AccountBalance
{
    public function __construct(
        public int $debitTotalMinor,
        public int $creditTotalMinor,
        public int $signedBalanceMinor,
        public NormalBalance $normalBalance,
        public int $displayBalanceMinor,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'debit_total_minor' => $this->debitTotalMinor,
            'credit_total_minor' => $this->creditTotalMinor,
            'signed_balance_minor' => $this->signedBalanceMinor,
            'normal_balance' => $this->normalBalance->value,
            'display_balance_minor' => $this->displayBalanceMinor,
        ];
    }
}
