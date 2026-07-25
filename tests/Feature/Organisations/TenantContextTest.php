<?php

use App\Domain\Organisations\Exceptions\BranchOutsideTenant;
use App\Domain\Organisations\Exceptions\TenantContextMissing;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;

test('organisation context can be set and retrieved', function () {
    $context = app(TenantContext::class);
    $organisation = Organisation::factory()->create();

    expect($context->hasOrganisation())->toBeFalse();

    $context->setOrganisation($organisation);

    expect($context->hasOrganisation())->toBeTrue()
        ->and($context->organisation()->is($organisation))->toBeTrue();
});

test('accessing a missing organisation context throws a clear exception', function () {
    app(TenantContext::class)->organisation();
})->throws(TenantContextMissing::class);

test('a valid branch may be set', function () {
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $context = app(TenantContext::class);

    $context->setOrganisation($organisation);
    $context->setBranch($branch);

    expect($context->branch()?->is($branch))->toBeTrue();
});

test('a branch from another organisation is rejected', function () {
    $context = app(TenantContext::class);
    $context->setOrganisation(Organisation::factory()->create());

    $context->setBranch(Branch::factory()->create());
})->throws(BranchOutsideTenant::class);

test('context may be cleared', function () {
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $context = app(TenantContext::class);

    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->clear();

    expect($context->hasOrganisation())->toBeFalse()
        ->and($context->branch())->toBeNull();
});
