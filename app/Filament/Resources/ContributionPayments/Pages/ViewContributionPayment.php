<?php

namespace App\Filament\Resources\ContributionPayments\Pages;

use App\Domain\Collections\Models\ContributionPayment;
use App\Filament\Resources\ContributionPayments\ContributionPaymentResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViewContributionPayment extends ViewRecord
{
    protected static string $resource = ContributionPaymentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $payment = $this->getRecord();

        if (! $payment instanceof ContributionPayment) {
            return;
        }

        $payment->load([
            'allocations' => fn (HasMany $query): HasMany => $query
                ->with('obligation')
                ->orderBy('allocation_order'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [ContributionPaymentResource::reversalAction()];
    }
}
