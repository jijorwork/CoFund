<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Enums\WelfareSequenceType;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Domain\Welfare\Models\WelfareEvent;
use App\Domain\Welfare\Services\CalculateWelfareBenefit;
use App\Domain\Welfare\Services\EvaluateWelfareEligibility;
use App\Domain\Welfare\Services\WelfareNumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateWelfareClaim
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private WelfareNumberGenerator $numbers, private EvaluateWelfareEligibility $eligibility, private CalculateWelfareBenefit $benefits) {}

    /** @param array<string,mixed> $attributes */
    public function handle(User $actor, WelfareEnrolment $enrolment, WelfareEvent $event, array $attributes): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsCreate->value);
        $organisation = $this->context->organisation();
        if ($enrolment->organisation_id !== $organisation->getKey() || $event->organisation_id !== $organisation->getKey() || $event->member_id !== $enrolment->member_id || $enrolment->product->status !== WelfareProductStatus::Active || ($event->eventType->welfare_product_id !== null && $event->eventType->welfare_product_id !== $enrolment->welfare_product_id)) {
            throw new InvalidWelfareOperation('Claim relationships are inconsistent.');
        }

        return DB::transaction(function () use ($actor, $enrolment, $event, $attributes, $organisation): WelfareClaim {
            $claim = WelfareClaim::query()->create([...$attributes, 'organisation_id' => $organisation->getKey(), 'branch_id' => $enrolment->branch_id, 'group_id' => $enrolment->group_id, 'claim_number' => $this->numbers->next($organisation, WelfareSequenceType::Claim, CarbonImmutable::parse($attributes['claim_date'])->year), 'member_id' => $enrolment->member_id, 'welfare_product_id' => $enrolment->welfare_product_id, 'welfare_enrolment_id' => $enrolment->getKey(), 'welfare_event_id' => $event->getKey(), 'welfare_event_type_id' => $event->welfare_event_type_id, 'currency_code' => $enrolment->product->currency_code, 'created_by_user_id' => $actor->getKey()]);
            $eligibility = $this->eligibility->evaluate($claim->load(['member', 'product', 'enrolment', 'event', 'eventType']));
            $benefit = $this->benefits->calculate($claim, $attributes['declared_cost_minor'] ?? null);
            $claim->update(['eligibility_status' => $eligibility['eligibility_status'], 'eligibility_snapshot' => $eligibility, 'eligible_benefit_minor' => min($benefit['eligible_benefit_minor'], $eligibility['maximum_eligible_benefit_minor']), 'benefit_snapshot' => $benefit, 'status' => WelfareClaimStatus::Draft]);

            return $claim->refresh();
        });
    }
}
