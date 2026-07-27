<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CreateBranchWallet;
use App\Domain\Wallets\Actions\CreateCollectorWallet;
use App\Domain\Wallets\Actions\CreateCustodianWallet;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Actions\CreateOrganisationWallet;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Queries\FindWalletByUuid;
use App\Domain\Wallets\Queries\ListWallets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListWalletsRequest;
use App\Http\Requests\Api\V1\StoreWalletRequest;
use App\Http\Resources\Api\V1\WalletResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    public function index(ListWalletsRequest $request, ListWallets $wallets): AnonymousResourceCollection
    {
        return WalletResource::collection($wallets->handle(
            $request->validated('filter', []),
            $request->validated('sort', 'name'),
        ))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(
        StoreWalletRequest $request,
        TenantContext $context,
        FindMemberByUuid $members,
        CreateMemberWallet $createMember,
        CreateOrganisationWallet $createOrganisation,
        CreateBranchWallet $createBranch,
        CreateCollectorWallet $createCollector,
        CreateCustodianWallet $createCustodian,
    ): WalletResource {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $ownerType = WalletOwnerType::from($request->validated('owner_type'));
        $walletType = WalletType::from($request->validated('wallet_type'));
        $branch = $this->branch($request->validated('branch_uuid'), $context);
        $wallet = match ($ownerType) {
            WalletOwnerType::Organisation => $createOrganisation->handle(
                $actor,
                $walletType,
                $request->validated('name'),
                $request->validated('code'),
            ),
            WalletOwnerType::Branch => $createBranch->handle(
                $actor,
                $branch ?? abort(422),
                $walletType,
                $request->validated('name'),
                $request->validated('code'),
            ),
            WalletOwnerType::Member => $createMember->handle(
                $actor,
                $members->handle($request->validated('owner_uuid')) ?? abort(404),
                $walletType,
                $request->validated('name'),
                $request->validated('code'),
            ),
            WalletOwnerType::CollectorUser => $createCollector->handle(
                $actor,
                $this->collector($request->validated('owner_uuid'), $context),
                $branch ?? abort(422),
                $request->validated('name'),
                $request->validated('code'),
            ),
            WalletOwnerType::CustodianUser => $createCustodian->handle(
                $actor,
                $this->collector($request->validated('owner_uuid'), $context),
                $branch ?? abort(422),
                $request->validated('name'),
                $request->validated('code'),
            ),
        };

        return WalletResource::make($wallet)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(
        Request $request,
        string $walletUuid,
        FindWalletByUuid $wallets,
        RoleAuthorization $authorization,
    ): WalletResource {
        $authorization->authorize(FoundationPermission::WalletsView->value);

        return WalletResource::make($wallets->handle($walletUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    private function branch(?string $uuid, TenantContext $context): ?Branch
    {
        return $uuid === null ? null : Branch::query()
            ->whereBelongsTo($context->organisation())
            ->where('public_id', $uuid)
            ->first() ?? abort(404);
    }

    private function collector(string $uuid, TenantContext $context): User
    {
        return User::query()
            ->where('public_id', $uuid)
            ->whereHas('organisationAccesses', fn ($query) => $query
                ->where('organisation_id', $context->organisation()->getKey())
                ->where('status', 'active'))
            ->first() ?? abort(404);
    }
}
