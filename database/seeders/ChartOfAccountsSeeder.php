<?php

namespace Database\Seeders;

use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(CreateDefaultChartOfAccounts $createDefaultChartOfAccounts): void
    {
        Organisation::query()
            ->orderBy('id')
            ->eachById(
                fn (Organisation $organisation) => $createDefaultChartOfAccounts->handle($organisation)
            );
    }
}
