<?php

namespace Database\Factories\Domain\Membership\Models;

use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Enums\MemberDocumentType;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberDocument> */
class MemberDocumentFactory extends Factory
{
    protected $model = MemberDocument::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'documentable_type' => 'member',
            'documentable_id' => Member::factory(),
            'organisation_id' => fn (array $attributes): int => Member::query()->findOrFail((int) $attributes['documentable_id'])->organisation_id,
            'document_type' => MemberDocumentType::SupportingDocument,
            'original_name' => 'document.pdf',
            'stored_name' => fake()->uuid().'.pdf',
            'disk' => 'local',
            'path' => 'membership/test/document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', 'test-document'),
            'verification_status' => DocumentVerificationStatus::Pending,
            'uploaded_by_user_id' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => DocumentVerificationStatus::Pending,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => DocumentVerificationStatus::Verified,
            'verified_at' => now(),
            'verified_by_user_id' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => DocumentVerificationStatus::Rejected,
            'rejection_reason' => 'Document is unreadable.',
        ]);
    }
}
