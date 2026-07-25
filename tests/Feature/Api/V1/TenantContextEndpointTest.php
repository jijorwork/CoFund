<?php

use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;

test('the public health endpoint does not require authentication or tenant context', function () {
    $this->getJson(route('api.v1.health'))
        ->assertOk()
        ->assertHeader('X-Request-ID')
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.application', config('app.name'))
        ->assertJsonPath('data.api_version', 'v1')
        ->assertJsonStructure(['meta' => ['request_id']]);
});

test('the context endpoint requires authentication', function () {
    $this->getJson(route('api.v1.context'))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated')
        ->assertJsonPath('error.message', 'Authentication is required.')
        ->assertJsonStructure(['error' => ['details'], 'meta' => ['request_id']]);
});

test('a missing organisation header is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('api.v1.context'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');
});

test('invalid and inaccessible organisations return the same generic response', function () {
    $user = User::factory()->create();
    $inaccessibleOrganisation = Organisation::factory()->create();

    $invalid = $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => '01912345-6789-7000-8000-000000000000',
        ]);
    $inaccessible = $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => $inaccessibleOrganisation->public_id,
        ]);

    $invalid->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid')
        ->assertJsonPath('error.message', 'The selected organisation context is unavailable.');
    $inaccessible->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid')
        ->assertJsonPath('error.message', 'The selected organisation context is unavailable.');
});

test('a suspended organisation is rejected', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->suspended()->create();
    OrganisationUser::factory()->for($user)->for($organisation)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => $organisation->public_id,
        ])
        ->assertForbidden();
});

test('inactive organisation access is rejected', function (
    OrganisationAccessStatus $status,
) {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    OrganisationUser::factory()->for($user)->for($organisation)->create([
        'status' => $status,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => $organisation->public_id,
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');
})->with([
    'invited' => OrganisationAccessStatus::Invited,
    'suspended' => OrganisationAccessStatus::Suspended,
    'revoked' => OrganisationAccessStatus::Revoked,
]);

test('valid organisation context succeeds', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    OrganisationUser::factory()->for($user)->for($organisation)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => $organisation->public_id,
        ])
        ->assertOk()
        ->assertJsonPath('data.organisation_uuid', $organisation->public_id)
        ->assertJsonPath('data.organisation_name', $organisation->name)
        ->assertJsonPath('data.branch_uuid', null)
        ->assertJsonPath('data.branch_name', null)
        ->assertJsonStructure(['meta' => ['request_id']]);
});

test('valid organisation and branch context succeeds', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    OrganisationUser::factory()->for($user)->for($organisation)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => $organisation->public_id,
            'X-Branch-ID' => $branch->public_id,
        ])
        ->assertOk()
        ->assertJsonPath('data.branch_uuid', $branch->public_id)
        ->assertJsonPath('data.branch_name', $branch->name);
});

test('a cross organisation branch is rejected', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherBranch = Branch::factory()->create();
    OrganisationUser::factory()->for($user)->for($organisation)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => $organisation->public_id,
            'X-Branch-ID' => $otherBranch->public_id,
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');
});

test('a suspended branch is rejected', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->suspended()->create();
    OrganisationUser::factory()->for($user)->for($organisation)->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.context'), [
            'X-Organisation-ID' => $organisation->public_id,
            'X-Branch-ID' => $branch->public_id,
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');
});
