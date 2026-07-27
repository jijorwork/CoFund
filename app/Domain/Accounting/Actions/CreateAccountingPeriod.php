<?php

namespace App\Domain\Accounting\Actions;

use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Organisations\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateAccountingPeriod
{
    public function __construct(private TenantContext $context) {}

    public function handle(string $name, CarbonImmutable $startsOn, CarbonImmutable $endsOn): AccountingPeriod
    {
        if ($endsOn->isBefore($startsOn)) {
            throw new InvalidFinancialOperation;
        }

        return DB::transaction(function () use ($name, $startsOn, $endsOn): AccountingPeriod {
            $organisation = $this->context->organisation();
            $overlaps = AccountingPeriod::query()
                ->whereBelongsTo($organisation)
                ->whereDate('starts_on', '<=', $endsOn->toDateString())
                ->whereDate('ends_on', '>=', $startsOn->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($overlaps) {
                throw new InvalidFinancialOperation('Accounting periods cannot overlap.');
            }

            return AccountingPeriod::query()->create([
                'organisation_id' => $organisation->getKey(),
                'name' => $name,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'status' => AccountingPeriodStatus::Open,
            ]);
        });
    }
}
