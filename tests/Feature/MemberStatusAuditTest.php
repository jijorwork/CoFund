<?php

use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Auditing\RecordMemberStatusAudit;
use App\Domain\Membership\Actions\SuspendMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('member status changes create tenant scoped immutable audit events', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->active()->for($organisation)->create();

    app(SuspendMember::class)->handle($member, $actor, 'Review required');

    $event = AuditEvent::query()->sole();
    expect($event->organisation_id)->toBe($organisation->id)
        ->and($event->actor_user_id)->toBe($actor->id)
        ->and($event->subject_public_id)->toBe($member->public_id)
        ->and($event->event_key)->toBe('member.status_changed')
        ->and($event->details)->toBe([
            'from_status' => 'active',
            'to_status' => 'suspended',
            'reason' => 'Review required',
        ])
        ->and($member->statusHistories()->count())->toBe(1);

    expect(fn () => $event->update(['event_key' => 'changed']))
        ->toThrow(LogicException::class);
    expect(fn () => $event->delete())
        ->toThrow(LogicException::class);
});

test('a failed audit write rolls back the member transition and its history', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->active()->for($organisation)->create();

    $audit = Mockery::mock(RecordMemberStatusAudit::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(RecordMemberStatusAudit::class, $audit);

    expect(fn () => app(SuspendMember::class)->handle($member, $actor, 'Review required'))
        ->toThrow(RuntimeException::class);
    expect($member->refresh()->status->value)->toBe('active')
        ->and($member->statusHistories()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0);
});
