<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationPriority;
use App\Domain\Notifications\ValueObjects\NotificationEventDefinition;
use InvalidArgumentException;

class NotificationEventRegistry
{
    /** @return array<string, NotificationEventDefinition> */
    public function all(): array
    {
        $inApp = [NotificationChannel::InApp];
        $confirmation = [NotificationChannel::InApp, NotificationChannel::Email];

        return collect([
            $this->definition('member.created', NotificationCategory::Membership, $inApp, false, true, 'member'),
            $this->definition('member.activated', NotificationCategory::Membership, $confirmation, false, false, 'member'),
            $this->definition('member.suspended', NotificationCategory::Membership, $confirmation, true, false, 'member', NotificationPriority::High),
            $this->definition('contribution.received', NotificationCategory::Contributions, $confirmation, true, false, 'member'),
            $this->definition('contribution.receipt_issued', NotificationCategory::Contributions, $confirmation, true, false, 'member'),
            $this->definition('contribution.obligation_due', NotificationCategory::Contributions, $confirmation, false, true, 'member'),
            $this->definition('collector.variance_detected', NotificationCategory::Reconciliation, $inApp, true, false, 'branch_permission', NotificationPriority::High),
            $this->definition('expense.approval_required', NotificationCategory::Expenses, $inApp, false, false, 'permission', NotificationPriority::High),
            $this->definition('expense.approved', NotificationCategory::Expenses, $confirmation, false, false, 'user'),
            $this->definition('loan.application_submitted', NotificationCategory::Loans, $inApp, false, false, 'permission'),
            $this->definition('loan.approved', NotificationCategory::Loans, $confirmation, false, false, 'member'),
            $this->definition('loan.disbursed', NotificationCategory::Loans, $confirmation, true, false, 'member', NotificationPriority::High),
            $this->definition('loan.repayment_received', NotificationCategory::Loans, $confirmation, true, false, 'member'),
            $this->definition('loan.repayment_due', NotificationCategory::Loans, $confirmation, false, true, 'member'),
            $this->definition('welfare.claim_submitted', NotificationCategory::Welfare, $inApp, false, false, 'permission'),
            $this->definition('welfare.claim_approved', NotificationCategory::Welfare, $confirmation, false, false, 'member'),
            $this->definition('welfare.payment_completed', NotificationCategory::Welfare, $confirmation, true, false, 'member'),
            $this->definition('withdrawal.request_submitted', NotificationCategory::Withdrawals, $inApp, false, false, 'permission'),
            $this->definition('withdrawal.approved', NotificationCategory::Withdrawals, $confirmation, false, false, 'member'),
            $this->definition('withdrawal.payout_completed', NotificationCategory::Withdrawals, $confirmation, true, false, 'member'),
            $this->definition('accounting.integrity_issue_detected', NotificationCategory::Accounting, $inApp, true, false, 'permission', NotificationPriority::Critical),
            $this->definition('report.export_completed', NotificationCategory::Reports, $inApp, false, false, 'user'),
            $this->definition('report.export_failed', NotificationCategory::Reports, $inApp, false, false, 'user', NotificationPriority::High),
            $this->definition('security.role_changed', NotificationCategory::Security, $confirmation, true, false, 'user', NotificationPriority::Critical),
            $this->definition('security.login_alert', NotificationCategory::Security, $confirmation, true, false, 'user', NotificationPriority::Critical),
        ])->keyBy(fn (NotificationEventDefinition $definition): string => $definition->key)->all();
    }

    public function get(string $key): NotificationEventDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException('Unknown notification event key.');
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /** @param list<NotificationChannel> $channels */
    private function definition(
        string $key,
        NotificationCategory $category,
        array $channels,
        bool $mandatory,
        bool $digestible,
        string $resolver,
        NotificationPriority $priority = NotificationPriority::Normal,
    ): NotificationEventDefinition {
        return new NotificationEventDefinition(
            key: $key,
            category: $category,
            displayName: str($key)->replace(['.', '_'], ' ')->title()->toString(),
            description: 'CoFund '.str($key)->replace(['.', '_'], ' ')->toString().' notification.',
            defaultChannels: $channels,
            allowedChannels: [
                NotificationChannel::InApp,
                NotificationChannel::Email,
                NotificationChannel::Sms,
                NotificationChannel::Messaging,
            ],
            mandatory: $mandatory,
            digestible: $digestible,
            recipientResolver: $resolver,
            requiredPlaceholders: ['organisation.name', 'recipient.name', 'reference.number'],
            priority: $priority,
        );
    }
}
