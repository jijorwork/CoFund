<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Enums\WelfareSequenceType;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareEvent;
use App\Domain\Welfare\Models\WelfareEventType;
use App\Domain\Welfare\Services\WelfareNumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateWelfareEvent
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private WelfareNumberGenerator $numbers) {}

    /** @param array<string,mixed> $attributes */
    public function handle(User $actor, Member $member, WelfareEventType $eventType, array $attributes): WelfareEvent
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsCreate->value);
        $organisation = $this->context->organisation();
        $eventDate = CarbonImmutable::parse($attributes['event_date']);
        $reportedDate = CarbonImmutable::parse($attributes['reported_date']);
        if ($member->organisation_id !== $organisation->getKey() || $eventType->organisation_id !== $organisation->getKey() || $eventDate->gt($reportedDate)) {
            throw new InvalidWelfareOperation('The welfare event is invalid.');
        }
        $relatedPersonName = $attributes['related_person_name'] ?? null;
        unset($attributes['related_person_name']);

        return DB::transaction(fn (): WelfareEvent => WelfareEvent::query()->create([...$attributes, 'organisation_id' => $organisation->getKey(), 'branch_id' => $member->branch_id, 'member_id' => $member->getKey(), 'welfare_event_type_id' => $eventType->getKey(), 'event_number' => $this->numbers->next($organisation, WelfareSequenceType::Event, $eventDate->year), 'related_person_name_encrypted' => $relatedPersonName, 'created_by_user_id' => $actor->getKey()]));
    }
}
