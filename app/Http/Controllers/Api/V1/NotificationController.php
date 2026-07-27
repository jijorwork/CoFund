<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Notifications\Models\InAppNotification;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::NotificationsViewOwn->value);
        $query = InAppNotification::query()
            ->whereBelongsTo($context->organisation())
            ->where('user_id', $request->user()?->getKey());

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }
        if (! $request->boolean('archived')) {
            $query->whereNull('archived_at');
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        return NotificationResource::collection(
            $query->latest()->paginate(min($request->integer('per_page', 25), 100)),
        );
    }

    public function show(Request $request, string $notificationUuid, TenantContext $context, RoleAuthorization $authorization): NotificationResource
    {
        $authorization->authorize(FoundationPermission::NotificationsViewOwn->value);

        return NotificationResource::make($this->owned($request, $context, $notificationUuid));
    }

    public function markRead(Request $request, string $notificationUuid, TenantContext $context, RoleAuthorization $authorization): NotificationResource
    {
        $authorization->authorize(FoundationPermission::NotificationsReadOwn->value);
        $notification = $this->owned($request, $context, $notificationUuid);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return NotificationResource::make($notification->refresh());
    }

    public function markUnread(Request $request, string $notificationUuid, TenantContext $context, RoleAuthorization $authorization): NotificationResource
    {
        $authorization->authorize(FoundationPermission::NotificationsReadOwn->value);
        $notification = $this->owned($request, $context, $notificationUuid);
        $notification->update(['read_at' => null]);

        return NotificationResource::make($notification->refresh());
    }

    public function archive(Request $request, string $notificationUuid, TenantContext $context, RoleAuthorization $authorization): NotificationResource
    {
        $authorization->authorize(FoundationPermission::NotificationsArchiveOwn->value);
        $notification = $this->owned($request, $context, $notificationUuid);
        $notification->update(['archived_at' => $notification->archived_at ?? now()]);

        return NotificationResource::make($notification->refresh());
    }

    public function summary(Request $request, TenantContext $context, RoleAuthorization $authorization): JsonResponse
    {
        $authorization->authorize(FoundationPermission::NotificationsViewOwn->value);
        $base = InAppNotification::query()
            ->whereBelongsTo($context->organisation())
            ->where('user_id', $request->user()?->getKey());
        $unread = (clone $base)->whereNull('read_at')->whereNull('archived_at');

        return ApiResponse::success($request, [
            'unread_count' => (clone $unread)->count(),
            'unread_by_category' => (clone $unread)
                ->selectRaw('category, count(*) as aggregate')
                ->groupBy('category')
                ->pluck('aggregate', 'category'),
            'high_priority_unread_count' => (clone $unread)->where('priority', 'high')->count(),
            'critical_unread_count' => (clone $unread)->where('priority', 'critical')->count(),
            'latest_notification_at' => (clone $base)->max('created_at'),
            'archived_count' => $request->boolean('include_archived')
                ? (clone $base)->whereNotNull('archived_at')->count()
                : null,
        ]);
    }

    private function owned(Request $request, TenantContext $context, string $uuid): InAppNotification
    {
        return InAppNotification::query()
            ->whereBelongsTo($context->organisation())
            ->where('user_id', $request->user()?->getKey())
            ->where('public_id', $uuid)
            ->firstOrFail();
    }
}
