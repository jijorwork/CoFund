<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\ReportRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportExportRequest;
use App\Http\Resources\Api\V1\ReportExportResource;
use App\Jobs\GenerateReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::ReportsExportsView->value);
        $exports = ReportExport::query()
            ->whereBelongsTo($context->organisation())
            ->where('requested_by_user_id', $request->user()?->getKey())
            ->latest('requested_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ReportExportResource::collection($exports);
    }

    public function store(
        ReportExportRequest $request,
        TenantContext $context,
        RoleAuthorization $authorization,
        ReportRegistry $registry,
    ): ReportExportResource {
        $authorization->authorize(FoundationPermission::ReportsExportsCreate->value);
        $attributes = $request->validated();
        $authorization->authorize($registry->get((string) $attributes['report_key'])->permission);
        $export = ReportExport::query()->create([
            ...$attributes,
            'organisation_id' => $context->organisation()->getKey(),
            'requested_by_user_id' => $request->user()?->getKey(),
            'acting_role_id' => $context->role()->getKey(),
            'status' => ReportExportStatus::Pending,
            'currency_code' => $context->organisation()->currency_code,
            'requested_at' => now(),
        ]);
        GenerateReportExport::dispatch($export->getKey());

        return ReportExportResource::make($export);
    }

    public function show(Request $request, string $exportUuid, TenantContext $context, RoleAuthorization $authorization): ReportExportResource
    {
        $authorization->authorize(FoundationPermission::ReportsExportsView->value);

        return ReportExportResource::make($this->owned($request, $context, $exportUuid));
    }

    public function download(Request $request, string $exportUuid, TenantContext $context, RoleAuthorization $authorization): StreamedResponse
    {
        $authorization->authorize(FoundationPermission::ReportsExportsDownload->value);
        $export = $this->owned($request, $context, $exportUuid);
        if (
            $export->status !== ReportExportStatus::Completed
            || $export->storage_path === null
            || $export->expires_at?->isPast()
            || ! Storage::disk('local')->exists($export->storage_path)
        ) {
            throw ValidationException::withMessages(['export' => ['The export is not available for download.']]);
        }

        return Storage::disk('local')->download($export->storage_path, $export->file_name);
    }

    public function cancel(Request $request, string $exportUuid, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::ReportsExportsCancel->value);
        $export = $this->owned($request, $context, $exportUuid);
        if (! in_array($export->status, [ReportExportStatus::Pending, ReportExportStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => ['Only pending or processing exports may be cancelled.']]);
        }
        $export->update(['status' => ReportExportStatus::Cancelled]);

        return response()->json(['data' => ['uuid' => $export->public_id, 'status' => ReportExportStatus::Cancelled->value]]);
    }

    private function owned(Request $request, TenantContext $context, string $uuid): ReportExport
    {
        return ReportExport::query()
            ->whereBelongsTo($context->organisation())
            ->where('requested_by_user_id', $request->user()?->getKey())
            ->where('public_id', $uuid)
            ->firstOrFail();
    }
}
