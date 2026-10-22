<?php

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Exceptions\InvalidReceiptOperation;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Collections\Services\BuildContributionReceiptView;
use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class PrepareReceiptReissue
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private BuildContributionReceiptView $builder,
    ) {}

    /**
     * @return array{
     *     receipt: Receipt,
     *     view: array<string, mixed>,
     *     reissued_at: CarbonImmutable
     * }
     */
    public function handle(Receipt $receipt): array
    {
        $this->authorization->authorize(FoundationPermission::ReceiptsReissue->value);
        $contextBranchId = $this->context->branch()?->getKey();
        $collectorUserId = $this->context->roleAssignment()->user_id;

        if (
            $receipt->organisation_id !== $this->context->organisation()->getKey()
            || (
                $this->context->roleAssignment()->branch_id !== null
                && $receipt->branch_id !== $contextBranchId
            )
            || (
                $this->context->role()->name === SystemRole::Collector->value
                && (
                    $receipt->contribution_payment_id === null
                    || ! $receipt->payment()
                        ->where('collected_by_user_id', $collectorUserId)
                        ->exists()
                )
            )
        ) {
            throw new InvalidReceiptOperation('The receipt is unavailable in the active context.');
        }

        $reissuedAt = CarbonImmutable::now();
        $view = $this->builder->build($receipt);

        AuditEvent::query()->create([
            'organisation_id' => $receipt->organisation_id,
            'branch_id' => $receipt->branch_id,
            'actor_user_id' => $collectorUserId,
            'acting_role_id' => $this->context->role()->getKey(),
            'role_assignment_id' => $this->context->roleAssignment()->getKey(),
            'event_key' => 'receipt.reissued',
            'subject_type' => 'receipt',
            'subject_public_id' => $receipt->public_id,
            'details' => [
                'receipt_number' => $receipt->receipt_number,
                'reissued_at' => $reissuedAt->toIso8601String(),
            ],
        ]);

        Log::info('Receipt reissued', [
            'receipt_uuid' => $receipt->public_id,
            'organisation_id' => $receipt->organisation_id,
            'branch_id' => $receipt->branch_id,
            'actor_user_id' => $collectorUserId,
            'active_role_id' => $this->context->roleAssignment()->role_id,
            'reissued_at' => $reissuedAt->toIso8601String(),
        ]);

        return [
            'receipt' => $receipt,
            'view' => $view,
            'reissued_at' => $reissuedAt,
        ];
    }
}
