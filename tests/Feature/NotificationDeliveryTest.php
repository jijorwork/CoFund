<?php

use App\Domain\Notifications\Actions\CreateNotificationDispatch;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\InAppNotification;
use App\Domain\Notifications\Models\NotificationDeliveryAttempt;
use App\Domain\Notifications\Models\NotificationDispatch;
use App\Domain\Notifications\Services\DeliverNotificationChannel;
use App\Domain\Notifications\ValueObjects\NotificationDispatchData;
use App\Domain\Organisations\Models\Organisation;
use App\Jobs\DeliverInAppNotification;
use App\Mail\NotificationEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function createDeliveryTestDispatch(Organisation $organisation, User $recipient, array $channels): NotificationDispatch
{
    return app(CreateNotificationDispatch::class)->handle(new NotificationDispatchData(
        organisation: $organisation,
        branch: null,
        eventKey: 'contribution.received',
        sourceType: 'receipt',
        sourceUuid: fake()->uuid(),
        recipient: $recipient,
        title: 'Contribution received',
        body: 'Your contribution was received.',
        channels: $channels,
    ));
}

test('in-app delivery completes once and records one immutable attempt', function () {
    Queue::fake();
    $organisation = Organisation::factory()->create();
    $recipient = User::factory()->create();
    $dispatch = createDeliveryTestDispatch($organisation, $recipient, [NotificationChannel::InApp]);
    $delivery = $dispatch->deliveries()->sole();
    Queue::assertPushed(DeliverInAppNotification::class);

    $service = app(DeliverNotificationChannel::class);
    $service->handle($delivery->public_id, NotificationChannel::InApp);
    $service->handle($delivery->public_id, NotificationChannel::InApp);

    expect($delivery->refresh()->status)->toBe('delivered')
        ->and($delivery->attempt_count)->toBe(1)
        ->and($delivery->attempts()->count())->toBe(1)
        ->and($dispatch->refresh()->status)->toBe('completed')
        ->and(InAppNotification::query()->where('notification_dispatch_id', $dispatch->id)->count())->toBe(1);
    expect(fn () => $delivery->attempts()->sole()->update(['status' => 'changed']))->toThrow(LogicException::class);
});

test('verified email delivery sends once and unverified email is marked unavailable', function () {
    Queue::fake();
    Mail::fake();
    $organisation = Organisation::factory()->create();
    $verified = User::factory()->create(['email_verified_at' => now()]);
    $dispatch = createDeliveryTestDispatch($organisation, $verified, [NotificationChannel::Email]);
    $delivery = $dispatch->deliveries()->sole();

    app(DeliverNotificationChannel::class)->handle($delivery->public_id, NotificationChannel::Email);
    expect($delivery->refresh()->status)->toBe('delivered');
    Mail::assertSent(NotificationEmail::class, fn (NotificationEmail $mail): bool => $mail->hasTo($verified->email)
        && $mail->title === 'Contribution received'
        && $mail->body === 'Your contribution was received.');
    (new NotificationEmail('Contribution received', 'Your contribution was received.'))
        ->assertSeeInText('Your contribution was received.');

    $unverified = User::factory()->create(['email_verified_at' => null]);
    $unavailable = createDeliveryTestDispatch($organisation, $unverified, [NotificationChannel::Email]);
    expect($unavailable->deliveries()->sole()->status)->toBe('unavailable');
    Mail::assertSentCount(1);
});

test('retryable email failures stop cleanly after the maximum recorded attempts', function () {
    Queue::fake();
    $organisation = Organisation::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $dispatch = createDeliveryTestDispatch($organisation, $recipient, [NotificationChannel::Email]);
    $delivery = $dispatch->deliveries()->sole();
    Mail::shouldReceive('to')->times(5)->andThrow(new RuntimeException('Mail transport unavailable'));
    $service = app(DeliverNotificationChannel::class);

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        if ($attempt > 1) {
            $this->travel([2 => 61, 3 => 301, 4 => 901][$attempt])->seconds();
        }
        expect(fn () => $service->handle($delivery->public_id, NotificationChannel::Email))
            ->toThrow(RuntimeException::class);
        expect($delivery->refresh()->status)->toBe('retry_pending');
        if ($attempt === 1) {
            expect(fn () => $service->handle($delivery->public_id, NotificationChannel::Email))
                ->toThrow(RuntimeException::class);
            expect($delivery->refresh()->attempt_count)->toBe(1);
        }
    }

    $this->travel(3601)->seconds();
    $service->handle($delivery->public_id, NotificationChannel::Email);
    expect($delivery->refresh()->status)->toBe('permanently_failed')
        ->and($delivery->attempt_count)->toBe(5)
        ->and($delivery->attempts()->count())->toBe(5)
        ->and($dispatch->refresh()->status)->toBe('failed');
});

test('an in-flight delivery is not duplicated and an abandoned attempt can recover', function () {
    Queue::fake();
    $organisation = Organisation::factory()->create();
    $recipient = User::factory()->create();
    $dispatch = createDeliveryTestDispatch($organisation, $recipient, [NotificationChannel::InApp]);
    $delivery = $dispatch->deliveries()->sole();
    $delivery->update(['status' => 'sending', 'attempt_count' => 1]);
    $attempt = NotificationDeliveryAttempt::query()->create([
        'organisation_id' => $organisation->getKey(),
        'notification_delivery_id' => $delivery->getKey(),
        'attempt_number' => 1,
        'started_at' => now(),
        'status' => 'started',
    ]);

    $service = app(DeliverNotificationChannel::class);
    expect(fn () => $service->handle($delivery->public_id, NotificationChannel::InApp))
        ->toThrow(RuntimeException::class);
    expect($delivery->refresh()->attempt_count)->toBe(1)
        ->and(InAppNotification::query()->count())->toBe(0);

    $this->travel(6)->minutes();
    $service->handle($delivery->public_id, NotificationChannel::InApp);
    expect($attempt->refresh()->status)->toBe('timed_out')
        ->and($delivery->refresh()->status)->toBe('delivered')
        ->and($delivery->attempt_count)->toBe(2)
        ->and($delivery->attempts()->count())->toBe(2)
        ->and(InAppNotification::query()->count())->toBe(1);
    expect(fn () => $attempt->delete())->toThrow(LogicException::class);
});
