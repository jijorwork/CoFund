<?php

use App\Domain\Organisations\Enums\SettingValueType;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationSetting;
use App\Domain\Organisations\Services\BranchDirectory;
use App\Domain\Organisations\Services\OrganisationSettingReader;
use App\Domain\Organisations\Tenancy\TenantContext;

test('tenant aware branch queries cannot return another organisations branches', function () {
    $activeOrganisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($activeOrganisation)->create();
    $otherBranch = Branch::factory()->for($otherOrganisation)->create();

    app(TenantContext::class)->setOrganisation($activeOrganisation);
    $branches = app(BranchDirectory::class)->all();

    expect($branches->modelKeys())->toContain($activeBranch->id)
        ->not->toContain($otherBranch->id)
        ->and(app(BranchDirectory::class)->findByPublicId($otherBranch->public_id))->toBeNull();
});

test('organisation settings are typed and cannot be read across organisations', function () {
    $activeOrganisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();

    $activeSetting = OrganisationSetting::factory()->for($activeOrganisation)->create([
        'key' => 'reminders_enabled',
        'value' => 'true',
        'value_type' => SettingValueType::Boolean,
    ]);
    OrganisationSetting::factory()->for($activeOrganisation)->create([
        'key' => 'grace_days',
        'value' => '5',
        'value_type' => SettingValueType::Integer,
    ]);
    OrganisationSetting::factory()->for($activeOrganisation)->create([
        'key' => 'threshold',
        'value' => '12.50',
        'value_type' => SettingValueType::Decimal,
    ]);
    OrganisationSetting::factory()->for($activeOrganisation)->create([
        'key' => 'receipt_options',
        'value' => '{"sms":true}',
        'value_type' => SettingValueType::Json,
    ]);
    OrganisationSetting::factory()->for($otherOrganisation)->create([
        'key' => 'private_label',
        'value' => 'hidden',
    ]);

    app(TenantContext::class)->setOrganisation($activeOrganisation);
    $reader = app(OrganisationSettingReader::class);

    expect($activeSetting->organisation->is($activeOrganisation))->toBeTrue()
        ->and($activeOrganisation->settings()->whereKey($activeSetting->id)->exists())->toBeTrue()
        ->and($reader->get('reminders_enabled'))->toBeTrue()
        ->and($reader->get('grace_days'))->toBe(5)
        ->and($reader->get('threshold'))->toBe('12.50')
        ->and($reader->get('receipt_options'))->toBe(['sms' => true])
        ->and($reader->get('private_label', 'default'))->toBe('default');
});
