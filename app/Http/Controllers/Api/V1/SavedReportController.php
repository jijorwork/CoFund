<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Enums\ReportRunStatus;
use App\Domain\Reports\Enums\SavedReportVisibility;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Services\ExecuteRegisteredReport;
use App\Domain\Reports\Services\ReportRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SavedReportRequest;
use App\Http\Resources\Api\V1\SavedReportResource;
use App\Http\Responses\ReportResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class SavedReportController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ReportsView->value);
        $user = $request->user();
        $reports = SavedReport::query()
            ->whereBelongsTo($context->organisation())
            ->where(fn (Builder $query) => $query
                ->where('owner_user_id', $user?->getKey())
                ->orWhere('visibility', SavedReportVisibility::Organisation)
                ->orWhere(fn (Builder $query) => $query
                    ->where('visibility', SavedReportVisibility::Role)
                    ->where('acting_role_id', $context->role()->getKey())))
            ->latest()
            ->paginate(min($request->integer('per_page', 25), 100));

        return SavedReportResource::collection($reports);
    }

    public function store(SavedReportRequest $request, TenantContext $context, RoleAuthorization $authorization, ReportRegistry $registry): SavedReportResource
    {
        $authorization->authorize(FoundationPermission::ReportsSavedCreate->value);
        $attributes = $request->validated();
        $visibility = SavedReportVisibility::from((string) $attributes['visibility']);
        $reportKey = (string) $attributes['report_key'];
        $this->authorizeVisibility($authorization, $visibility);
        $authorization->authorize($registry->get($reportKey)->permission);
        $report = SavedReport::query()->create([
            ...$attributes,
            'organisation_id' => $context->organisation()->getKey(),
            'owner_user_id' => $request->user()?->getKey(),
            'acting_role_id' => $visibility === SavedReportVisibility::Role
                ? $context->role()->getKey()
                : null,
        ]);

        return SavedReportResource::make($report);
    }

    public function show(Request $request, string $savedReportUuid, TenantContext $context, RoleAuthorization $authorization): SavedReportResource
    {
        $authorization->authorize(FoundationPermission::ReportsView->value);

        return SavedReportResource::make($this->visible($request, $context, $savedReportUuid));
    }

    public function update(SavedReportRequest $request, string $savedReportUuid, TenantContext $context, RoleAuthorization $authorization, ReportRegistry $registry): SavedReportResource
    {
        $authorization->authorize(FoundationPermission::ReportsSavedUpdate->value);
        $report = $this->owned($request, $context, $savedReportUuid);
        $attributes = $request->validated();
        if (isset($attributes['visibility'])) {
            $visibility = SavedReportVisibility::from((string) $attributes['visibility']);
            $this->authorizeVisibility($authorization, $visibility);
            $attributes['acting_role_id'] = $visibility === SavedReportVisibility::Role
                ? $context->role()->getKey()
                : null;
        }
        if (isset($attributes['report_key'])) {
            $authorization->authorize($registry->get((string) $attributes['report_key'])->permission);
        }
        $report->update($attributes);

        return SavedReportResource::make($report->refresh());
    }

    public function destroy(Request $request, string $savedReportUuid, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::ReportsSavedDelete->value);
        $this->owned($request, $context, $savedReportUuid)->delete();

        return response()->json(status: 204);
    }

    public function duplicate(Request $request, string $savedReportUuid, TenantContext $context, RoleAuthorization $authorization): SavedReportResource
    {
        $authorization->authorize(FoundationPermission::ReportsSavedCreate->value);
        $source = $this->visible($request, $context, $savedReportUuid);
        $copy = $source->replicate(['public_id', 'owner_user_id', 'acting_role_id', 'visibility', 'is_default']);
        $copy->name = $source->name.' Copy';
        $copy->owner_user_id = $request->user()?->getKey();
        $copy->acting_role_id = null;
        $copy->visibility = SavedReportVisibility::Private;
        $copy->is_default = false;
        $copy->save();

        return SavedReportResource::make($copy);
    }

    public function run(
        Request $request,
        string $savedReportUuid,
        TenantContext $context,
        RoleAuthorization $authorization,
        ReportRegistry $registry,
        ExecuteRegisteredReport $executor,
    ): JsonResponse {
        $report = $this->visible($request, $context, $savedReportUuid);
        $authorization->authorize($registry->get($report->report_key)->permission);
        $started = hrtime(true);
        $run = ReportRun::query()->create([
            'organisation_id' => $context->organisation()->getKey(),
            'user_id' => $request->user()?->getKey(),
            'acting_role_id' => $context->role()->getKey(),
            'saved_report_id' => $report->getKey(),
            'report_key' => $report->report_key,
            'filters' => $report->filters,
            'status' => ReportRunStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $result = $executor->execute($report->report_key, $report->filters);
            $rowCount = is_countable($result['data']) ? count($result['data']) : null;
            $run->update([
                'status' => ReportRunStatus::Completed,
                'row_count' => $rowCount,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'completed_at' => now(),
            ]);

            return ReportResponse::make($request, $report->report_key, $result['data'], $result['summary'], $result['range'], $context->organisation()->currency_code, $report->filters);
        } catch (InvalidArgumentException $exception) {
            $run->update([
                'status' => ReportRunStatus::Failed,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'failed_at' => now(),
                'failure_reason' => 'Report execution is unavailable for this definition.',
            ]);

            throw ValidationException::withMessages(['report_key' => [$exception->getMessage()]]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => ReportRunStatus::Failed,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'failed_at' => now(),
                'failure_reason' => 'The report could not be completed.',
            ]);

            throw $exception;
        }
    }

    private function authorizeVisibility(RoleAuthorization $authorization, SavedReportVisibility $visibility): void
    {
        if ($visibility === SavedReportVisibility::Role) {
            $authorization->authorize(FoundationPermission::ReportsSavedShareRole->value);
        }
        if ($visibility === SavedReportVisibility::Organisation) {
            $authorization->authorize(FoundationPermission::ReportsSavedShareOrganisation->value);
        }
    }

    private function visible(Request $request, TenantContext $context, string $uuid): SavedReport
    {
        return SavedReport::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->where(fn (Builder $query) => $query
                ->where('owner_user_id', $request->user()?->getKey())
                ->orWhere('visibility', SavedReportVisibility::Organisation)
                ->orWhere(fn (Builder $query) => $query
                    ->where('visibility', SavedReportVisibility::Role)
                    ->where('acting_role_id', $context->role()->getKey())))
            ->firstOrFail();
    }

    private function owned(Request $request, TenantContext $context, string $uuid): SavedReport
    {
        return SavedReport::query()
            ->whereBelongsTo($context->organisation())
            ->where('owner_user_id', $request->user()?->getKey())
            ->where('public_id', $uuid)
            ->firstOrFail();
    }
}
