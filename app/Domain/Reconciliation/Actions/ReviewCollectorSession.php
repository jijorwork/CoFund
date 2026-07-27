<?php

namespace App\Domain\Reconciliation\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Reconciliation\Enums\CollectorSessionStatus;
use App\Domain\Reconciliation\Exceptions\InvalidReconciliationOperation;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Models\User;

class ReviewCollectorSession
{
    public function __construct(private RoleAuthorization $authorization) {}

    public function approve(User $actor, CollectorCollectionSession $session): CollectorCollectionSession
    {
        $this->authorization->authorize(FoundationPermission::CollectionSessionsReview->value);

        if ($session->status !== CollectorSessionStatus::Submitted || $session->submitted_by_user_id === $actor->getKey()) {
            throw new InvalidReconciliationOperation('The submitted session requires independent review.');
        }

        $session->update(['status' => CollectorSessionStatus::UnderReview]);

        return $session->refresh();
    }

    public function reject(User $actor, CollectorCollectionSession $session, string $reason): CollectorCollectionSession
    {
        $this->authorization->authorize(FoundationPermission::CollectionSessionsReview->value);

        if (! in_array($session->status, [CollectorSessionStatus::Submitted, CollectorSessionStatus::UnderReview], true)
            || $session->submitted_by_user_id === $actor->getKey()
            || trim($reason) === '') {
            throw new InvalidReconciliationOperation('The session cannot be rejected by this actor.');
        }

        $session->update([
            'status' => CollectorSessionStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by_user_id' => $actor->getKey(),
            'rejection_reason' => $reason,
        ]);

        return $session->refresh();
    }
}
