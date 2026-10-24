<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Models\ContextualAccessToken;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ExternalTokenController extends Controller
{
    public function index(Request $request, RoleAuthorization $authorization, TenantContext $tenantContext): JsonResponse
    {
        $authorization->authorize(FoundationPermission::ApiTokensManage->value);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tokens = $user->tokens()
            ->where('organisation_id', $tenantContext->organisation()->getKey())
            ->where('branch_id', $tenantContext->branch()?->getKey())
            ->where('role_assignment_id', $tenantContext->roleAssignment()->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'uuid' => $token->public_id,
                'name' => $token->name,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])->all();

        return ApiResponse::success($request, ['tokens' => $tokens]);
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        RoleAuthorization $authorization,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ApiTokensManage->value);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'expires_in_days' => ['required', 'integer', 'between:1,90'],
        ]);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $token = DB::transaction(function () use ($user, $validated, $tenantContext): array {
            $plainTextToken = $user->generateTokenString();
            $created = $user->tokens()->create([
                'name' => $validated['name'],
                'token' => hash('sha256', $plainTextToken),
                'abilities' => ['read'],
                'expires_at' => now()->addDays($validated['expires_in_days']),
                'organisation_id' => $tenantContext->organisation()->getKey(),
                'branch_id' => $tenantContext->branch()?->getKey(),
                'role_assignment_id' => $tenantContext->roleAssignment()->getKey(),
                'role_id' => $tenantContext->role()->getKey(),
            ]);
            $acting = ActingContext::from($user, $tenantContext);
            AuditEvent::query()->create([
                'organisation_id' => $acting->organisationId,
                'branch_id' => $acting->branchId,
                'actor_user_id' => $acting->userId,
                'acting_role_id' => $acting->roleId,
                'role_assignment_id' => $acting->roleAssignmentId,
                'event_key' => 'api_token.issued',
                'subject_type' => 'api_token',
                'subject_public_id' => $created->public_id,
                'details' => ['expires_at' => $created->expires_at?->toIso8601String()],
            ]);

            return [
                'uuid' => $created->public_id,
                'name' => $created->name,
                'plain_text_token' => $created->getKey().'|'.$plainTextToken,
                'expires_at' => $created->expires_at?->toIso8601String(),
                'organisation_uuid' => $tenantContext->organisation()->public_id,
                'branch_uuid' => $tenantContext->branch()?->public_id,
                'role_assignment_uuid' => $tenantContext->roleAssignment()->public_id,
            ];
        });

        return response()->json([
            'data' => $token,
            'meta' => ['request_id' => ApiResponse::requestId($request)],
        ], 201);
    }

    public function destroy(
        Request $request,
        RoleAuthorization $authorization,
        TenantContext $tenantContext,
        string $tokenUuid,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ApiTokensManage->value);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        $token = $user->tokens()
            ->where('organisation_id', $tenantContext->organisation()->getKey())
            ->where('branch_id', $tenantContext->branch()?->getKey())
            ->where('role_assignment_id', $tenantContext->roleAssignment()->getKey())
            ->where('public_id', $tokenUuid)
            ->first();

        if (! $token instanceof ContextualAccessToken) {
            abort(404);
        }

        DB::transaction(function () use ($token, $user, $tenantContext): void {
            $acting = ActingContext::from($user, $tenantContext);
            AuditEvent::query()->create([
                'organisation_id' => $acting->organisationId,
                'branch_id' => $acting->branchId,
                'actor_user_id' => $acting->userId,
                'acting_role_id' => $acting->roleId,
                'role_assignment_id' => $acting->roleAssignmentId,
                'event_key' => 'api_token.revoked',
                'subject_type' => 'api_token',
                'subject_public_id' => $token->public_id,
                'details' => [],
            ]);
            $token->delete();
        });

        return ApiResponse::success($request, ['revoked' => true]);
    }
}
