<?php

namespace App\Domain\Accounting\Data;

use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Organisations\Models\Branch;
use Carbon\CarbonImmutable;

final readonly class TransactionData
{
    /** @param array<string, mixed>|null $metadata */
    public function __construct(
        public ?Branch $branch,
        public FinancialTransactionType $transactionType,
        public string $currencyCode,
        public CarbonImmutable $transactionDate,
        public string $description,
        public string $idempotencyKey,
        public ?string $externalReference = null,
        public ?FinancialReferenceType $referenceType = null,
        public ?string $referenceId = null,
        public ?array $metadata = null,
    ) {}
}
