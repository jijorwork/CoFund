<?php

use App\Domain\Membership\Actions\AddMemberContact;
use App\Domain\Membership\Actions\AddMemberToGroup;
use App\Domain\Membership\Actions\RemoveMemberFromGroup;
use App\Domain\Membership\Actions\UpdateMemberContact;
use App\Domain\Membership\Enums\GroupMembershipStatus;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Services\BeneficiaryAllocationService;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('a member joins multiple groups while duplicate active membership is rejected', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->for($organisation)->create();
    $first = Group::factory()->for($organisation)->create();
    $second = Group::factory()->for($organisation)->create();
    $action = app(AddMemberToGroup::class);

    $action->handle($first, $member, $actor);
    $action->handle($second, $member, $actor);

    expect($member->groupMemberships()->count())->toBe(2);

    $action->handle($first, $member, $actor);
})->throws(InvalidMembershipOperation::class);

test('leaving a group preserves membership history', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->for($organisation)->create();
    $group = Group::factory()->for($organisation)->create();
    $membership = app(AddMemberToGroup::class)->handle($group, $member, $actor);

    app(RemoveMemberFromGroup::class)->handle($group, $member);

    expect($membership->refresh()->status)->toBe(GroupMembershipStatus::Left)
        ->and($membership->left_at)->not->toBeNull()
        ->and($membership->exists)->toBeTrue();
});

test('cross organisation group membership is rejected', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);

    app(AddMemberToGroup::class)->handle(
        Group::factory()->for($organisation)->create(),
        Member::factory()->for(Organisation::factory())->create(),
        $actor,
    );
})->throws(InvalidMembershipOperation::class);

test('beneficiary totals use decimal safe allocation and updates do not double count', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->for($organisation)->create();
    $add = app(AddMemberContact::class);
    $first = $add->handle($member, [
        'contact_type' => MemberContactType::Beneficiary->value,
        'full_name' => 'First Beneficiary',
        'relationship' => 'Child',
        'phone' => '0240000000',
        'percentage_entitlement' => '60.00',
    ]);
    $add->handle($member, [
        'contact_type' => MemberContactType::Beneficiary->value,
        'full_name' => 'Second Beneficiary',
        'relationship' => 'Child',
        'phone' => '0240000001',
        'percentage_entitlement' => '40.00',
    ]);

    app(UpdateMemberContact::class)->handle($member, $first, [
        'percentage_entitlement' => '50.00',
    ]);

    expect(app(BeneficiaryAllocationService::class)->currentAllocatedPercentage($member))
        ->toBe('90.00');
});

test('beneficiary totals cannot exceed one hundred percent', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishMembershipDomainContext($actor, $organisation);
    $member = Member::factory()->for($organisation)->create();
    MemberContact::factory()->for($member)->beneficiary('80.00')->create();

    app(AddMemberContact::class)->handle($member, [
        'contact_type' => MemberContactType::Beneficiary->value,
        'full_name' => 'Excess Beneficiary',
        'relationship' => 'Child',
        'phone' => '0240000000',
        'percentage_entitlement' => '20.01',
    ]);
})->throws(InvalidMembershipOperation::class);
