<?php

namespace App\Filament\Resources\ContributionEnrolments\Pages;

use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\CalculateContributionPosition;
use App\Filament\Resources\ContributionEnrolments\ContributionEnrolmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContributionEnrolment extends ViewRecord
{
    protected static string $resource = ContributionEnrolmentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $enrolment = $this->getRecord();

        if (! $enrolment instanceof MemberPlanEnrolment) {
            return;
        }

        $enrolment->load([
            'obligations' => fn ($query) => $query->orderBy('sequence_number'),
        ]);
        $position = app(CalculateContributionPosition::class)->calculate($enrolment);
        $enrolment->setAttribute('position_status', $position->status->value);
        $enrolment->setAttribute('position_expected_minor', $position->expectedToDateMinor);
        $enrolment->setAttribute('position_satisfied_minor', $position->satisfiedToDateMinor);
        $enrolment->setAttribute('position_outstanding_minor', $position->outstandingToDateMinor);
        $enrolment->setAttribute('position_overdue_count', $position->overdueObligationCount);
        $enrolment->setAttribute('position_upcoming_count', $position->upcomingObligationCount);
        $enrolment->setAttribute('position_next_due_date', $position->nextDueDate);
        $enrolment->setAttribute('position_next_amount_minor', $position->nextExpectedAmountMinor);
    }

    protected function getHeaderActions(): array
    {
        return ContributionEnrolmentResource::lifecycleActions();
    }
}
