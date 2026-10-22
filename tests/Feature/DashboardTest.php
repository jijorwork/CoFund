<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('users without workspace access see a clear dashboard status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertSee('does not currently have access')
        ->assertDontSee('placeholder-pattern');
});

test('organisation staff reach the real administration dashboard', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    OrganisationUserRole::factory()->for($user)->for($organisation)->state([
        'role_id' => Role::findByName(SystemRole::OrganisationAdmin->value)->getKey(),
    ])->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('/admin');
});

test('platform administrators reach the platform workspace', function () {
    $user = User::factory()->create();
    $user->assignRole(SystemRole::PlatformAdmin->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('/platform');
});
