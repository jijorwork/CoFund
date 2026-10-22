<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Organisations\Actions\ChangeOrganisationStatus;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Exceptions\InvalidOrganisationStatusTransition;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationStatusHistory;
use App\Filament\Platform\Resources\Organisations\Pages\ListOrganisations;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('only a verified global platform administrator can enter the platform panel', function () {
    $platformAdministrator = User::factory()->create();
    $platformAdministrator->assignRole(SystemRole::PlatformAdmin->value);
    $ordinaryUser = User::factory()->create();

    $this->actingAs($platformAdministrator)
        ->get('/platform')
        ->assertSuccessful();

    $this->actingAs($ordinaryUser)
        ->get('/platform')
        ->assertForbidden();
});

test('a global platform administrator is not granted tenant panel access', function () {
    $platformAdministrator = User::factory()->create();
    $platformAdministrator->assignRole(SystemRole::PlatformAdmin->value);

    $this->actingAs($platformAdministrator)
        ->get('/admin')
        ->assertForbidden();
});

test('platform administrator approves an organisation with immutable audit history', function () {
    $platformAdministrator = User::factory()->create();
    $platformAdministrator->assignRole(SystemRole::PlatformAdmin->value);
    $organisation = Organisation::factory()->pending()->create();

    Filament::setCurrentPanel('platform');
    Filament::bootCurrentPanel();

    $this->actingAs($platformAdministrator);

    Livewire::test(ListOrganisations::class)
        ->assertActionVisible(TestAction::make('approve')->table($organisation))
        ->callAction(TestAction::make('approve')->table($organisation))
        ->assertHasNoActionErrors();

    $history = OrganisationStatusHistory::query()->sole();

    expect($organisation->refresh()->status)->toBe(OrganisationStatus::Active)
        ->and($history->from_status)->toBe(OrganisationStatus::Pending)
        ->and($history->to_status)->toBe(OrganisationStatus::Active)
        ->and($history->changed_by_user_id)->toBe($platformAdministrator->getKey())
        ->and($history->metadata)->toMatchArray(['source' => 'platform_filament'])
        ->and(fn () => $history->delete())->toThrow(LogicException::class);
});

test('organisation suspension requires a reason and non-platform users cannot transition status', function () {
    $platformAdministrator = User::factory()->create();
    $platformAdministrator->assignRole(SystemRole::PlatformAdmin->value);
    $ordinaryUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $action = app(ChangeOrganisationStatus::class);

    expect(fn () => $action->handle(
        $platformAdministrator,
        $organisation,
        OrganisationStatus::Suspended,
    ))->toThrow(InvalidOrganisationStatusTransition::class, 'A suspension reason is required.')
        ->and(fn () => $action->handle(
            $ordinaryUser,
            $organisation,
            OrganisationStatus::Suspended,
            'Regulatory review.',
        ))->toThrow(InvalidOrganisationStatusTransition::class, 'Only a platform administrator');
});
