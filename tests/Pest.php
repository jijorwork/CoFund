<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function establishMembershipDomainContext(
    User $actor,
    Organisation $organisation,
    ?Branch $branch = null,
): void {
    OrganisationUser::factory()->for($actor)->for($organisation)->create();
    $assignment = OrganisationUserRole::factory()
        ->for($actor)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName(SystemRole::OrganisationAdmin->value)->getKey(),
        ])->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment);
}

function establishAccountingContext(
    User $actor,
    Organisation $organisation,
    SystemRole $role = SystemRole::Treasurer,
): void {
    $assignment = OrganisationUserRole::factory()
        ->for($actor)
        ->for($organisation)
        ->state(['role_id' => Role::findByName($role->value)->getKey()])
        ->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment);
}

function selectMembershipApiContext(
    TestCase $test,
    User $user,
    Organisation $organisation,
    SystemRole $role,
    ?Branch $branch = null,
): void {
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName($role->value)->getKey(),
        ])->create();

    $test->actingAs($user)->postJson(route('api.v1.auth.context.store'), [
        'organisation_uuid' => $organisation->public_id,
        'branch_uuid' => $branch?->public_id,
        'role_assignment_uuid' => $assignment->public_id,
    ])->assertSuccessful();
}
