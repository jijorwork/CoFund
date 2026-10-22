<?php

namespace App\Filament\Resources\Accounting\Pages;

use App\Filament\Resources\Accounting\FinancialTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListFinancialTransactions extends ListRecords
{
    protected static string $resource = FinancialTransactionResource::class;
}
