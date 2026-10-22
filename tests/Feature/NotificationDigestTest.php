<?php

use App\Domain\Notifications\Actions\CreateNotificationDispatch;
use App\Domain\Notifications\Actions\SendDueNotificationDigests;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Models\NotificationDigest;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Services\DeliverNotificationChannel;
use App\Domain\Notifications\ValueObjects\NotificationDispatchData;
use App\Domain\Organisations\Models\Organisation;
use App\Mail\NotificationEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('digestible email notifications are grouped while in-app delivery remains immediate', function () {
    Queue::fake();
    Mail::fake();
    $this->travelTo(now()->startOfHour());
    $organisation = Organisation::factory()->create(['timezone' => 'UTC']);
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    NotificationPreference::query()->create([
        'organisation_id' => $organisation->id,
        'recipient_type' => 'user',
        'user_id' => $recipient->id,
        'category' => 'contributions',
        'email_enabled' => true,
        'in_app_enabled' => true,
        'digest_frequency' => 'hourly',
        'preference_scope_key' => fake()->uuid(),
    ]);

    $dispatches = collect([1, 2])->map(fn (int $number) => app(CreateNotificationDispatch::class)->handle(new NotificationDispatchData(
        organisation: $organisation,
        branch: null,
        eventKey: 'contribution.obligation_due',
        sourceType: 'obligation',
        sourceUuid: fake()->uuid(),
        recipient: $recipient,
        title: "Contribution {$number} due",
        body: "Pay contribution {$number}.",
        channels: [NotificationChannel::InApp, NotificationChannel::Email],
    )));

    foreach ($dispatches as $dispatch) {
        expect($dispatch->deliveries()->where('channel', 'email')->sole()->status)->toBe('digest_pending');
        app(DeliverNotificationChannel::class)->handle($dispatch->deliveries()->where('channel', 'in_app')->sole()->public_id, NotificationChannel::InApp);
        expect($dispatch->refresh()->status)->toBe('pending');
    }

    expect(app(SendDueNotificationDigests::class)->handle())->toBe(0);
    Mail::assertNothingSent();
    expect(NotificationDigest::query()->sole()->item_count)->toBe(2);

    $this->travel(61)->minutes();
    expect(app(SendDueNotificationDigests::class)->handle())->toBe(1);
    Mail::assertSent(NotificationEmail::class, 1);
    expect(NotificationDigest::query()->sole()->status)->toBe('sent');
    foreach ($dispatches as $dispatch) {
        expect($dispatch->refresh()->status)->toBe('completed')
            ->and($dispatch->deliveries()->where('channel', 'email')->sole()->status)->toBe('delivered');
    }
    expect(app(SendDueNotificationDigests::class)->handle())->toBe(0);
    Mail::assertSentCount(1);
});

test('mandatory email notifications bypass a digest preference', function () {
    Queue::fake();
    $organisation = Organisation::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    NotificationPreference::query()->create([
        'organisation_id' => $organisation->id,
        'recipient_type' => 'user',
        'user_id' => $recipient->id,
        'category' => 'contributions',
        'email_enabled' => true,
        'digest_frequency' => 'daily',
        'preference_scope_key' => fake()->uuid(),
    ]);

    $dispatch = app(CreateNotificationDispatch::class)->handle(new NotificationDispatchData(
        organisation: $organisation,
        branch: null,
        eventKey: 'contribution.received',
        sourceType: 'receipt',
        sourceUuid: fake()->uuid(),
        recipient: $recipient,
        title: 'Contribution received',
        body: 'Your contribution was received.',
        channels: [NotificationChannel::Email],
    ));

    expect($dispatch->policy_snapshot['digest_frequency'])->toBeNull()
        ->and($dispatch->deliveries()->sole()->status)->toBe('pending');
});
