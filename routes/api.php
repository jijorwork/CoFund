<?php

use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Api\V1\AuthContextController;
use App\Http\Controllers\Api\V1\ContributionEnrolmentController;
use App\Http\Controllers\Api\V1\ContributionEnrolmentLifecycleController;
use App\Http\Controllers\Api\V1\ContributionPlanController;
use App\Http\Controllers\Api\V1\ContributionPlanLifecycleController;
use App\Http\Controllers\Api\V1\ContributionScheduleController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\MemberContactController;
use App\Http\Controllers\Api\V1\MemberContributionSummaryController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberDocumentController;
use App\Http\Controllers\Api\V1\MemberLifecycleController;
use App\Http\Controllers\Api\V1\MemberUserLinkController;
use App\Http\Controllers\Api\V1\RevokeRoleAssignmentController;
use App\Http\Controllers\Api\V1\RoleAssignmentController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Resources\Api\V1\TenantContextResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', function (Request $request) {
        return ApiResponse::success($request, [
            'status' => 'ok',
            'application' => config('app.name'),
            'api_version' => 'v1',
        ]);
    })->name('health');

    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('auth/contexts', [AuthContextController::class, 'index'])
            ->name('auth.contexts.index');
        Route::post('auth/context', [AuthContextController::class, 'store'])
            ->name('auth.context.store');

        Route::middleware('tenant.context')->group(function () {
            Route::get('context', function (Request $request, TenantContext $tenantContext) {
                return TenantContextResource::make($tenantContext)->additional([
                    'meta' => ['request_id' => ApiResponse::requestId($request)],
                ]);
            })->name('context');

            Route::middleware('role.context')->group(function () {
                Route::get('roles', RoleController::class)->name('roles.index');
                Route::get('role-assignments', [RoleAssignmentController::class, 'index'])
                    ->name('role-assignments.index');
                Route::post('role-assignments', [RoleAssignmentController::class, 'store'])
                    ->name('role-assignments.store');
                Route::get(
                    'role-assignments/{roleAssignmentUuid}',
                    [RoleAssignmentController::class, 'show'],
                )->name('role-assignments.show');
                Route::post(
                    'role-assignments/{roleAssignmentUuid}/revoke',
                    RevokeRoleAssignmentController::class,
                )->name('role-assignments.revoke');

                Route::get('members', [MemberController::class, 'index'])->name('members.index');
                Route::post('members', [MemberController::class, 'store'])->name('members.store');
                Route::get('members/{memberUuid}', [MemberController::class, 'show'])->name('members.show');
                Route::patch('members/{memberUuid}', [MemberController::class, 'update'])->name('members.update');
                Route::post('members/{memberUuid}/activate', [MemberLifecycleController::class, 'activate'])->name('members.activate');
                Route::post('members/{memberUuid}/suspend', [MemberLifecycleController::class, 'suspend'])->name('members.suspend');
                Route::post('members/{memberUuid}/reinstate', [MemberLifecycleController::class, 'reinstate'])->name('members.reinstate');
                Route::post('members/{memberUuid}/mark-inactive', [MemberLifecycleController::class, 'markInactive'])->name('members.mark-inactive');
                Route::post('members/{memberUuid}/exit', [MemberLifecycleController::class, 'exit'])->name('members.exit');
                Route::post('members/{memberUuid}/mark-deceased', [MemberLifecycleController::class, 'markDeceased'])->name('members.mark-deceased');
                Route::post('members/{memberUuid}/link-user', [MemberUserLinkController::class, 'link'])->name('members.link-user');
                Route::post('members/{memberUuid}/unlink-user', [MemberUserLinkController::class, 'unlink'])->name('members.unlink-user');
                Route::get('members/{memberUuid}/contribution-summary', MemberContributionSummaryController::class)->name('members.contribution-summary');

                Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
                Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
                Route::get('groups/{groupUuid}', [GroupController::class, 'show'])->name('groups.show');
                Route::patch('groups/{groupUuid}', [GroupController::class, 'update'])->name('groups.update');
                Route::get('groups/{groupUuid}/members', [GroupController::class, 'members'])->name('groups.members.index');
                Route::post('groups/{groupUuid}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
                Route::post('groups/{groupUuid}/members/{memberUuid}/remove', [GroupController::class, 'removeMember'])->name('groups.members.remove');

                Route::get('members/{memberUuid}/contacts', [MemberContactController::class, 'index'])->name('members.contacts.index');
                Route::post('members/{memberUuid}/contacts', [MemberContactController::class, 'store'])->name('members.contacts.store');
                Route::get('members/{memberUuid}/contacts/{contactUuid}', [MemberContactController::class, 'show'])->name('members.contacts.show');
                Route::patch('members/{memberUuid}/contacts/{contactUuid}', [MemberContactController::class, 'update'])->name('members.contacts.update');
                Route::post('members/{memberUuid}/contacts/{contactUuid}/deactivate', [MemberContactController::class, 'deactivate'])->name('members.contacts.deactivate');
                Route::post('members/{memberUuid}/contacts/{contactUuid}/verify', [MemberContactController::class, 'verify'])->name('members.contacts.verify');

                Route::get('members/{memberUuid}/documents', [MemberDocumentController::class, 'index'])->name('members.documents.index');
                Route::post('members/{memberUuid}/documents', [MemberDocumentController::class, 'store'])->name('members.documents.store');
                Route::get('members/{memberUuid}/documents/{documentUuid}', [MemberDocumentController::class, 'show'])->name('members.documents.show');
                Route::get('members/{memberUuid}/documents/{documentUuid}/download', [MemberDocumentController::class, 'download'])->name('members.documents.download');
                Route::post('members/{memberUuid}/documents/{documentUuid}/verify', [MemberDocumentController::class, 'verify'])->name('members.documents.verify');
                Route::post('members/{memberUuid}/documents/{documentUuid}/reject', [MemberDocumentController::class, 'reject'])->name('members.documents.reject');

                Route::get('contribution-plans', [ContributionPlanController::class, 'index'])->name('contribution-plans.index');
                Route::post('contribution-plans', [ContributionPlanController::class, 'store'])->name('contribution-plans.store');
                Route::get('contribution-plans/{contributionPlanUuid}', [ContributionPlanController::class, 'show'])->name('contribution-plans.show');
                Route::patch('contribution-plans/{contributionPlanUuid}', [ContributionPlanController::class, 'update'])->name('contribution-plans.update');
                Route::post('contribution-plans/{contributionPlanUuid}/activate', [ContributionPlanLifecycleController::class, 'activate'])->name('contribution-plans.activate');
                Route::post('contribution-plans/{contributionPlanUuid}/suspend', [ContributionPlanLifecycleController::class, 'suspend'])->name('contribution-plans.suspend');
                Route::post('contribution-plans/{contributionPlanUuid}/reinstate', [ContributionPlanLifecycleController::class, 'reinstate'])->name('contribution-plans.reinstate');
                Route::post('contribution-plans/{contributionPlanUuid}/close', [ContributionPlanLifecycleController::class, 'close'])->name('contribution-plans.close');
                Route::post('contribution-plans/{contributionPlanUuid}/archive', [ContributionPlanLifecycleController::class, 'archive'])->name('contribution-plans.archive');

                Route::get('contribution-enrolments', [ContributionEnrolmentController::class, 'index'])->name('contribution-enrolments.index');
                Route::post('contribution-enrolments', [ContributionEnrolmentController::class, 'store'])->name('contribution-enrolments.store');
                Route::get('contribution-enrolments/{contributionEnrolmentUuid}', [ContributionEnrolmentController::class, 'show'])->name('contribution-enrolments.show');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/approve', [ContributionEnrolmentLifecycleController::class, 'approve'])->name('contribution-enrolments.approve');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/reject', [ContributionEnrolmentLifecycleController::class, 'reject'])->name('contribution-enrolments.reject');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/suspend', [ContributionEnrolmentLifecycleController::class, 'suspend'])->name('contribution-enrolments.suspend');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/reinstate', [ContributionEnrolmentLifecycleController::class, 'reinstate'])->name('contribution-enrolments.reinstate');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/end', [ContributionEnrolmentLifecycleController::class, 'end'])->name('contribution-enrolments.end');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/cancel', [ContributionEnrolmentLifecycleController::class, 'cancel'])->name('contribution-enrolments.cancel');
                Route::get('contribution-enrolments/{contributionEnrolmentUuid}/schedule', [ContributionScheduleController::class, 'index'])->name('contribution-enrolments.schedule.index');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/schedule/extend', [ContributionScheduleController::class, 'extend'])->name('contribution-enrolments.schedule.extend');
            });
        });
    });
});
