<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NotificationPreferenceRequest;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::NotificationPreferencesViewOwn->value);

        return NotificationPreferenceResource::collection(
            NotificationPreference::query()
                ->whereBelongsTo($context->organisation())
                ->where('recipient_type', 'user')
                ->where('user_id', $request->user()?->getKey())
                ->orderBy('category')
                ->orderBy('event_key')
                ->get(),
        );
    }

    public function store(NotificationPreferenceRequest $request, TenantContext $context, RoleAuthorization $authorization): NotificationPreferenceResource
    {
        $authorization->authorize(FoundationPermission::NotificationPreferencesUpdateOwn->value);
        $attributes = $request->validated();
        $userId = $request->user()?->getKey();
        $scopeKey = hash('sha256', implode('|', [
            $context->organisation()->getKey(),
            'user',
            $userId,
            $attributes['category'],
            $attributes['event_key'] ?? '*',
        ]));
        $preference = NotificationPreference::query()->updateOrCreate(
            ['preference_scope_key' => $scopeKey],
            [
                ...$attributes,
                'organisation_id' => $context->organisation()->getKey(),
                'recipient_type' => 'user',
                'user_id' => $userId,
                'member_id' => null,
            ],
        );

        return NotificationPreferenceResource::make($preference);
    }
}
