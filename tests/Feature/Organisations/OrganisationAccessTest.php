<?php

use App\Domain\Organisations\Actions\AssignUserToOrganisation;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Services\OrganisationAccessService;
use App\Models\User;
use Illuminate\Database\QueryException;

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
