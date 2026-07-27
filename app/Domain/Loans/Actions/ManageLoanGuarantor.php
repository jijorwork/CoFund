<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Loans\Services\EvaluateGuarantorEligibility;
use App\Domain\Membership\Models\Member;
use Illuminate\Support\Facades\DB;

class ManageLoanGuarantor
{
    public function __construct(private RoleAuthorization $authorization, private EvaluateGuarantorEligibility $eligibility) {}

    public function invite(LoanApplication $application, Member $member, int $amountMinor): LoanGuarantor
    {
        $this->authorization->authorize(FoundationPermission::LoanGuarantorsManage->value);
        $result = $this->eligibility->evaluate($member, $application, $amountMinor);
        if (! $result['eligible'] || $amountMinor <= 0) {
            throw new InvalidLoanOperation('The member cannot guarantee this application.');
        }
        $key = $application->getKey().':'.$member->getKey();

        return LoanGuarantor::query()->create(['organisation_id' => $application->organisation_id, 'loan_application_id' => $application->getKey(), 'guarantor_member_id' => $member->getKey(), 'guaranteed_amount_minor' => $amountMinor, 'status' => GuarantorStatus::Invited, 'invited_at' => now(), 'active_guarantor_key' => $key]);
    }

    public function respond(LoanGuarantor $guarantor, GuarantorStatus $status): LoanGuarantor
    {
        $this->authorization->authorize(FoundationPermission::LoanGuarantorsManage->value);
        if (! in_array($status, [GuarantorStatus::Accepted, GuarantorStatus::Declined], true) || $guarantor->status !== GuarantorStatus::Invited) {
            throw new InvalidLoanOperation;
        }

        return DB::transaction(function () use ($guarantor, $status): LoanGuarantor {
            $locked = LoanGuarantor::query()->with(['application.product', 'guarantor'])->lockForUpdate()->whereKey($guarantor->getKey())->firstOrFail();
            if ($status === GuarantorStatus::Accepted && ! $this->eligibility->evaluate($locked->guarantor, $locked->application, $locked->guaranteed_amount_minor)['eligible']) {
                throw new InvalidLoanOperation('Guarantor eligibility changed.');
            }
            $locked->update(['status' => $status, $status === GuarantorStatus::Accepted ? 'accepted_at' : 'declined_at' => now(), 'active_guarantor_key' => $status === GuarantorStatus::Accepted ? $locked->active_guarantor_key : null]);

            return $locked->refresh();
        });
    }

    public function revoke(LoanGuarantor $guarantor): LoanGuarantor
    {
        $this->authorization->authorize(FoundationPermission::LoanGuarantorsManage->value);
        if (! in_array($guarantor->status, [GuarantorStatus::Invited, GuarantorStatus::Accepted], true)) {
            throw new InvalidLoanOperation('Only an active guarantor commitment may be revoked.');
        }

        $guarantor->update([
            'status' => GuarantorStatus::Revoked,
            'active_guarantor_key' => null,
        ]);

        return $guarantor->refresh();
    }
}
