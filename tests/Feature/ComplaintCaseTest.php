<?php

use App\Domain\Complaints\Enums\ComplaintStatus;
use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Complaints\Models\ComplaintMessage;
use App\Domain\Organisations\Models\Organisation;
use Database\Seeders\ComplaintDemoSeeder;

test('complaint cases and append-only correspondence retain tenant references', function () {
    $organisation = Organisation::factory()->create();
    $case = ComplaintCase::factory()->for($organisation)->create();
    $message = ComplaintMessage::factory()->forCase($case)->create();

    expect($case->status)->toBe(ComplaintStatus::Open)
        ->and($message->organisation_id)->toBe($organisation->id)
        ->and($case->messages()->sole()->public_id)->toBe($message->public_id);
    expect(fn () => $message->update(['body' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $message->delete())->toThrow(LogicException::class);
});

test('complaint demo seeder creates local example correspondence', function () {
    $this->seed(ComplaintDemoSeeder::class);

    expect(ComplaintCase::query()->count())->toBe(1)
        ->and(ComplaintMessage::query()->count())->toBe(1)
        ->and(ComplaintCase::query()->sole()->messages()->sole()->visibility)->toBe('complainant');
});
