<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Expenses\Actions\ManagePettyCashRetirement;
use App\Domain\Expenses\Models\PettyCashRetirement;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ExpenseDecisionRequest;
use App\Http\Resources\Api\V1\PettyCashRetirementResource;
use App\Models\User;
use Illuminate\Http\Request;

class PettyCashRetirementController extends Controller
{
    public function submit(Request $request, string $retirementUuid, TenantContext $context, ManagePettyCashRetirement $action): PettyCashRetirementResource
    {
        return PettyCashRetirementResource::make($action->submit($this->actor($request), $this->find($context, $retirementUuid)));
    }

    public function approve(ExpenseDecisionRequest $request, string $retirementUuid, TenantContext $context, ManagePettyCashRetirement $action): PettyCashRetirementResource
    {
        $key = (string) $request->header('Idempotency-Key', '');
        abort_if($key === '', 422);
        $retirement = $this->find($context, $retirementUuid);

        return PettyCashRetirementResource::make($action->approve($this->actor($request), $retirement, $request->integer('amount_minor'), $request->integer('returned_cash_minor'), $key));
    }

    public function reject(ExpenseDecisionRequest $request, string $retirementUuid, TenantContext $context, ManagePettyCashRetirement $action): PettyCashRetirementResource
    {
        return PettyCashRetirementResource::make($action->reject($this->actor($request), $this->find($context, $retirementUuid), (string) $request->validated('reason')));
    }

    private function find(TenantContext $context, string $uuid): PettyCashRetirement
    {
        return PettyCashRetirement::query()->whereBelongsTo($context->organisation())->when($context->roleAssignment()->branch_id !== null, fn ($q) => $q->where('branch_id', $context->branch()?->getKey()))->where('public_id', $uuid)->with('items')->firstOrFail();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
