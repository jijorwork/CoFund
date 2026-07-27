<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Enums\WelfareSequenceType;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Domain\Welfare\Services\CalculateWelfareWaitingPeriod;
use App\Domain\Welfare\Services\WelfareNumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateWelfareEnrolment
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private WelfareNumberGenerator $numbers, private CalculateWelfareWaitingPeriod $waitingPeriod) {}

    public function handle(User $actor, Member $member, WelfareProduct $product, CarbonImmutable $coverageStart): WelfareEnrolment
    {
        $this->authorization->authorize(FoundationPermission::WelfareEnrolmentsCreate->value);
        $organisation = $this->context->organisation();
        if ($member->organisation_id !== $organisation->getKey() || $product->organisation_id !== $organisation->getKey() || $product->status !== WelfareProductStatus::Active || $member->status !== MemberStatus::Active || $coverageStart->lt(CarbonImmutable::today($organisation->timezone))) {
            throw new InvalidWelfareOperation('The welfare enrolment is not valid.');
        }
        if ($product->branch_id !== null && $product->branch_id !== $member->branch_id) {
            throw new InvalidWelfareOperation('The member is outside the product branch.');
        }

        return DB::transaction(function () use ($actor, $member, $product, $coverageStart, $organisation): WelfareEnrolment {
            $key = $organisation->getKey().':'.$member->getKey().':'.$product->getKey();
            if (WelfareEnrolment::query()->where('active_enrolment_key', $key)->lockForUpdate()->exists()) {
                throw new InvalidWelfareOperation('An active enrolment already exists.');
            }

            return WelfareEnrolment::query()->create(['organisation_id' => $organisation->getKey(), 'branch_id' => $member->branch_id, 'group_id' => $product->group_id, 'member_id' => $member->getKey(), 'welfare_product_id' => $product->getKey(), 'enrolment_number' => $this->numbers->next($organisation, WelfareSequenceType::Enrolment, $coverageStart->year), 'enrolled_at' => CarbonImmutable::today($organisation->timezone), 'coverage_start_date' => $coverageStart, 'waiting_period_end_date' => $this->waitingPeriod->endDate($product, $coverageStart), 'active_enrolment_key' => $key, 'created_by_user_id' => $actor->getKey()]);
        }, attempts: 3);
    }
}
