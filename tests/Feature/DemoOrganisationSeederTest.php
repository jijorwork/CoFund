<?php

use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;
use Database\Seeders\DemoOrganisationSeeder;
use Illuminate\Support\Facades\Hash;

test('demo organisations seed usable tenant contexts and remain idempotent', function () {
    app(DemoOrganisationSeeder::class)->run();

    expect(Organisation::query()->count())->toBe(3)
        ->and(Branch::query()->count())->toBe(3)
        ->and(User::query()->count())->toBe(8)
        ->and(OrganisationUser::query()->count())->toBe(8)
        ->and(OrganisationUserRole::query()->count())->toBe(7)
        ->and(OrganisationUser::query()->where('status', OrganisationAccessStatus::Active)->count())->toBe(7)
        ->and(OrganisationUser::query()->where('status', OrganisationAccessStatus::Invited)->count())->toBe(1);

    $administrator = User::query()->where('email', 'admin.adom@cofund.test')->sole();

    expect(Hash::check(DemoOrganisationSeeder::PASSWORD, $administrator->password))->toBeTrue()
        ->and($administrator->email_verified_at)->not->toBeNull()
        ->and($administrator->organisationAccesses()->active()->count())->toBe(1)
        ->and($administrator->organisationRoleAssignments()->currentlyValid()->count())->toBe(1);

    $hope = Organisation::query()->where('code', 'HOPE-SUSU')->sole();
    $hope->update(['status' => OrganisationStatus::Active]);

    app(DemoOrganisationSeeder::class)->run();

    expect(Organisation::query()->count())->toBe(3)
        ->and(Branch::query()->count())->toBe(3)
        ->and(User::query()->count())->toBe(8)
        ->and(OrganisationUser::query()->count())->toBe(8)
        ->and(OrganisationUserRole::query()->count())->toBe(7)
        ->and($hope->refresh()->status)->toBe(OrganisationStatus::Active);
});
