<?php

use App\Domain\Membership\Actions\ActivateMember;
use App\Domain\Membership\Actions\ReinstateMember;
use App\Domain\Membership\Actions\SuspendMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberStatusHistory;
use App\Domain\Notifications\Actions\CreateNotificationDispatch;
use App\Domain\Notifications\Models\NotificationDispatch;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('linked members receive a separate notification for every activation and suspension', function () {
    Queue::fake();
    $actor = User::factory()->create();
    $recipient = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->pending()->for($organisation)->create(['user_id' => $recipient->getKey()]);

    app(ActivateMember::class)->handle($member, $actor);
    app(SuspendMember::class)->handle($member, $actor, 'Sensitive internal review');
    app(ReinstateMember::class)->handle($member, $actor);
    app(SuspendMember::class)->handle($member, $actor, 'Another internal review');

    $dispatches = NotificationDispatch::query()->where('organisation_id', $organisation->getKey())->orderBy('id')->get();
    expect($dispatches)->toHaveCount(4)
        ->and($dispatches->pluck('event_key')->all())->toBe([
            'member.activated', 'member.suspended', 'member.activated', 'member.suspended',
        ])
        ->and($dispatches->pluck('source_uuid')->unique()->count())->toBe(4)
        ->and($dispatches->pluck('recipient_uuid')->unique()->all())->toBe([$recipient->public_id]);
    expect($dispatches->pluck('body')->implode(' '))->not->toContain('Sensitive internal review');
});

test('unlinked members do not create notification dispatches', function () {
    Queue::fake();
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->pending()->for($organisation)->create(['user_id' => null]);

    app(ActivateMember::class)->handle($member, $actor);
    app(SuspendMember::class)->handle($member, $actor, 'Review required');

    expect(NotificationDispatch::query()->count())->toBe(0);
});

test('membership notifications retain the member branch and organisation', function () {
    Queue::fake();
    $actor = User::factory()->create();
    $recipient = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    establishMembershipDomainContext($actor, $organisation, $branch);
    $member = Member::factory()->active()->for($organisation)->for($branch)->create(['user_id' => $recipient->getKey()]);

    app(SuspendMember::class)->handle($member, $actor, 'Internal review');

    $dispatch = NotificationDispatch::query()->sole();
    expect($dispatch->organisation_id)->toBe($organisation->getKey())
        ->and($dispatch->branch_id)->toBe($branch->getKey())
        ->and($dispatch->user_id)->toBe($recipient->getKey())
        ->and($dispatch->event_key)->toBe('member.suspended');
});

test('a failed notification dispatch rolls back the linked member transition', function () {
    Queue::fake();
    $actor = User::factory()->create();
    $recipient = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->pending()->for($organisation)->create(['user_id' => $recipient->getKey()]);
    $notifications = Mockery::mock(CreateNotificationDispatch::class);
    $notifications->shouldReceive('handle')->once()->andThrow(new RuntimeException('Notification unavailable'));
    app()->instance(CreateNotificationDispatch::class, $notifications);

    expect(fn () => app(ActivateMember::class)->handle($member, $actor))
        ->toThrow(RuntimeException::class);
    expect($member->refresh()->status->value)->toBe('pending')
        ->and(MemberStatusHistory::query()->count())->toBe(0)
        ->and(NotificationDispatch::query()->count())->toBe(0);
});
