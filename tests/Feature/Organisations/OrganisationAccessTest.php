<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Actions\AssignUserToOrganisation;
use App\Domain\Organisations\Actions\ChangeOrganisationAccessStatus;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Exceptions\InvalidOrganisationAccess;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationAccessStatusHistory;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Services\OrganisationAccessService;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Database\QueryException;

function organisationAccessAdministrator(
    User $user,
    Organisation $organisation,
): OrganisationUserRole {
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'role_id' => Role::findByName(SystemRole::OrganisationAdmin->value)->getKey(),
        ])
        ->create();

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment->load('role'));

    return $assignment;
}

test('a user can belong to multiple organisations', function () {
    $user = User::factory()->create();
    $organisations = Organisation::factory()->count(2)->create();
    $assign = app(AssignUserToOrganisation::class);

    foreach ($organisations as $organisation) {
        $assign->handle(
            $organisation,
            $user,
            OrganisationAccessStatus::Active,
            now(),
        );
    }

    expect($user->organisations()->count())->toBe(2)
        ->and($user->organisationAccesses()->count())->toBe(2)
        ->and($organisations->first()->users()->whereKey($user->id)->exists())->toBeTrue();
});

test('duplicate user organisation access is rejected', function () {
    $access = OrganisationUser::factory()->create();

    expect(fn () => OrganisationUser::factory()->create([
        'organisation_id' => $access->organisation_id,
        'user_id' => $access->user_id,
    ]))->toThrow(QueryException::class);
});

test('only active organisation access is accepted', function (
    OrganisationAccessStatus $status,
    bool $expected,
) {
    $access = OrganisationUser::factory()->create(['status' => $status]);

    expect(app(OrganisationAccessService::class)->hasActiveAccess(
        $access->user,
        $access->organisation,
    ))->toBe($expected);
})->with([
    'active' => [OrganisationAccessStatus::Active, true],
    'invited' => [OrganisationAccessStatus::Invited, false],
    'suspended' => [OrganisationAccessStatus::Suspended, false],
    'revoked' => [OrganisationAccessStatus::Revoked, false],
]);

test('organisation access transitions are tenant authorised and append audited history', function () {
    $this->seed(SystemAuthorizationSeeder::class);

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = organisationAccessAdministrator($actor, $organisation);
    $access = OrganisationUser::factory()
        ->for($target)
        ->for($organisation)
        ->create();

    $changed = app(ChangeOrganisationAccessStatus::class)->handle(
        $access,
        OrganisationAccessStatus::Suspended,
        $actor,
        'Access paused during an internal review.',
    );

    $history = OrganisationAccessStatusHistory::query()->sole();

    expect($changed->status)->toBe(OrganisationAccessStatus::Suspended)
        ->and($history->organisation_id)->toBe($organisation->id)
        ->and($history->organisation_user_id)->toBe($access->id)
        ->and($history->from_status)->toBe(OrganisationAccessStatus::Active)
        ->and($history->to_status)->toBe(OrganisationAccessStatus::Suspended)
        ->and($history->reason)->toBe('Access paused during an internal review.')
        ->and($history->changed_by_user_id)->toBe($actor->id)
        ->and($history->acting_role_id)->toBe($assignment->role_id)
        ->and($history->public_id)->toBeUuid()
        ->and(fn () => $history->update(['reason' => 'Changed']))
        ->toThrow(LogicException::class)
        ->and(fn () => $history->delete())
        ->toThrow(LogicException::class);
});

test('organisation access transitions reject missing reasons invalid transitions and self lockout', function () {
    $this->seed(SystemAuthorizationSeeder::class);

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    organisationAccessAdministrator($actor, $organisation);
    $targetAccess = OrganisationUser::factory()->for($target)->for($organisation)->create();
    $actorAccess = OrganisationUser::query()
        ->whereBelongsTo($organisation)
        ->whereBelongsTo($actor)
        ->firstOrFail();
    $changeStatus = app(ChangeOrganisationAccessStatus::class);

    expect(fn () => $changeStatus->handle(
        $targetAccess,
        OrganisationAccessStatus::Suspended,
        $actor,
    ))->toThrow(InvalidOrganisationAccess::class, 'A reason is required')
        ->and(fn () => $changeStatus->handle(
            $targetAccess,
            OrganisationAccessStatus::Active,
            $actor,
        ))->toThrow(InvalidOrganisationAccess::class, 'transition is not allowed')
        ->and(fn () => $changeStatus->handle(
            $actorAccess,
            OrganisationAccessStatus::Suspended,
            $actor,
            'Attempted self suspension.',
        ))->toThrow(InvalidOrganisationAccess::class, 'cannot change their own');

    expect(OrganisationAccessStatusHistory::query()->count())->toBe(0);
});

test('revoked organisation access is terminal but suspended access may reactivate', function () {
    $this->seed(SystemAuthorizationSeeder::class);

    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    organisationAccessAdministrator($actor, $organisation);
    $changeStatus = app(ChangeOrganisationAccessStatus::class);
    $suspended = OrganisationUser::factory()
        ->for(User::factory())
        ->for($organisation)
        ->suspended()
        ->create();
    $revoked = OrganisationUser::factory()
        ->for(User::factory())
        ->for($organisation)
        ->revoked()
        ->create();

    expect($changeStatus->handle(
        $suspended,
        OrganisationAccessStatus::Active,
        $actor,
    )->status)->toBe(OrganisationAccessStatus::Active)
        ->and(fn () => $changeStatus->handle(
            $revoked,
            OrganisationAccessStatus::Active,
            $actor,
        ))->toThrow(InvalidOrganisationAccess::class, 'transition is not allowed');
});
