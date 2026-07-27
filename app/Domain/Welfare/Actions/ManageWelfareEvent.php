<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareEventStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareEvent;
use App\Models\User;

class ManageWelfareEvent
{
    public function __construct(private RoleAuthorization $authorization) {}

    /** @param array<string,mixed> $attributes */
    public function update(WelfareEvent $event, array $attributes): WelfareEvent
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsUpdate->value);
        if ($event->status === WelfareEventStatus::Verified) {
            throw new InvalidWelfareOperation('Verified events are immutable.');
        } $event->update($attributes);

        return $event->refresh();
    }

    public function verify(User $actor, WelfareEvent $event, ?string $notes): WelfareEvent
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsVerify->value);
        if ($event->created_by_user_id === $actor->getKey()) {
            throw new InvalidWelfareOperation('Event creator cannot verify the event.');
        } $event->update(['status' => WelfareEventStatus::Verified, 'verified_at' => now(), 'verified_by_user_id' => $actor->getKey(), 'verification_notes' => $notes]);

        return $event->refresh();
    }

    public function reject(User $actor, WelfareEvent $event, string $reason): WelfareEvent
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsVerify->value);
        if (trim($reason) === '') {
            throw new InvalidWelfareOperation('A rejection reason is required.');
        } $event->update(['status' => WelfareEventStatus::Rejected, 'verified_at' => now(), 'verified_by_user_id' => $actor->getKey(), 'verification_notes' => $reason]);

        return $event->refresh();
    }

    public function cancel(WelfareEvent $event, string $reason): WelfareEvent
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsCancel->value);
        if (trim($reason) === '') {
            throw new InvalidWelfareOperation('A cancellation reason is required.');
        } $event->update(['status' => WelfareEventStatus::Cancelled, 'verification_notes' => $reason]);

        return $event->refresh();
    }
}
