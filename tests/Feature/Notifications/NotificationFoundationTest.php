<?php

use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Services\NotificationEventRegistry;
use App\Domain\Notifications\Services\RenderNotificationTemplate;

test('the notification event registry exposes the supported domain events', function () {
    $registry = app(NotificationEventRegistry::class);

    expect($registry->all())
        ->toHaveKey('contribution.received')
        ->toHaveKey('loan.disbursed')
        ->toHaveKey('welfare.payment_completed')
        ->toHaveKey('withdrawal.payout_completed')
        ->toHaveKey('security.role_changed');
});

test('notification templates render only allow-listed placeholders', function () {
    config()->set('app.url', 'https://cofund.test');
    $template = new NotificationTemplate([
        'event_key' => 'contribution.received',
        'channel' => 'in_app',
        'body_template' => 'Hello {{ recipient.name }}, receipt {{ reference.number }} from {{ organisation.name }}.',
        'action_url_template' => 'https://cofund.test/receipts/{{ reference.number }}',
    ]);

    $rendered = app(RenderNotificationTemplate::class)->render($template, [
        'recipient.name' => '<Member>',
        'reference.number' => 'RCT-001',
        'organisation.name' => 'CoFund Group',
    ]);

    expect($rendered->body)
        ->toBe('Hello &lt;Member&gt;, receipt RCT-001 from CoFund Group.')
        ->and($rendered->actionUrl)->toBe('https://cofund.test/receipts/RCT-001');
});

test('notification templates reject unknown placeholder values', function () {
    $template = new NotificationTemplate([
        'event_key' => 'contribution.received',
        'channel' => 'in_app',
        'body_template' => '{{ recipient.name }} {{ reference.number }} {{ organisation.name }}',
    ]);

    app(RenderNotificationTemplate::class)->render($template, [
        'recipient.name' => 'Member',
        'reference.number' => 'RCT-001',
        'organisation.name' => 'CoFund Group',
        'member.identification_number' => 'secret',
    ]);
})->throws(InvalidArgumentException::class);
