<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Models\ReportExport;
use App\Filament\Resources\ReportExports\ReportExportResource;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function establishExportContext(User $user, Organisation $organisation, ?Branch $branch = null): void
{
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->id,
            'role_id' => Role::findByName(SystemRole::Treasurer->value)->id,
        ])
        ->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));
}

test('report export history stays scoped to owner, tenant, branch, and report permission', function () {
    $actor = User::factory()->create();
    $otherUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    establishExportContext($actor, $organisation, $branch);
    $this->actingAs($actor);

    $own = ReportExport::factory()->for($organisation)->for($actor, 'requestedBy')->create([
        'report_key' => 'trial-balance',
        'filters' => ['branch_uuid' => $branch->public_id],
    ]);
    $otherOwner = ReportExport::factory()->for($organisation)->for($otherUser, 'requestedBy')->create();
    $otherTenant = ReportExport::factory()->for($otherOrganisation)->for($actor, 'requestedBy')->create();
    $otherBranchExport = ReportExport::factory()->for($organisation)->for($actor, 'requestedBy')->create([
        'filters' => ['branch_uuid' => $otherBranch->public_id],
    ]);
    $unscopedExport = ReportExport::factory()->for($organisation)->for($actor, 'requestedBy')->create();

    expect(ReportExportResource::getEloquentQuery()->pluck('public_id')->all())->toBe([$own->public_id]);
    expect(ReportExportResource::canView($own))->toBeTrue()
        ->and(ReportExportResource::canView($otherOwner))->toBeFalse()
        ->and(ReportExportResource::canView($otherTenant))->toBeFalse()
        ->and(ReportExportResource::canView($otherBranchExport))->toBeFalse()
        ->and(ReportExportResource::canView($unscopedExport))->toBeFalse();
    $this->get(ReportExportResource::getUrl('view', ['record' => $own->public_id]))->assertSuccessful();
    $this->get(ReportExportResource::getUrl('view', ['record' => $otherOwner->public_id]))->assertNotFound();
    $this->get(ReportExportResource::getUrl('view', ['record' => $otherBranchExport->public_id]))->assertNotFound();
});

test('completed exports download through the scoped resource and expired exports stay unavailable', function () {
    Storage::fake('local');
    $actor = User::factory()->create();
    $otherUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishExportContext($actor, $organisation);
    $this->actingAs($actor);
    $completed = ReportExport::factory()->for($organisation)->for($actor, 'requestedBy')->completed()->create();
    Storage::disk('local')->put($completed->storage_path, "account,balance\nCash,100\n");
    $otherOwner = ReportExport::factory()->for($organisation)->for($otherUser, 'requestedBy')->completed()->create();
    $expired = ReportExport::factory()->for($organisation)->for($actor, 'requestedBy')->expired()->create();

    expect(ReportExportResource::canDownload($completed))->toBeTrue()
        ->and(ReportExportResource::canDownload($otherOwner))->toBeFalse()
        ->and(ReportExportResource::canDownload($expired))->toBeFalse();
    ob_start();
    ReportExportResource::download($completed)->sendContent();
    $contents = ob_get_clean();
    expect($contents)->toBe("account,balance\nCash,100\n");
    expect(fn () => ReportExportResource::download($otherOwner))->toThrow(ModelNotFoundException::class);
});
