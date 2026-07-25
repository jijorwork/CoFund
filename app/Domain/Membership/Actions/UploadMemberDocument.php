<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadMemberDocument
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(Member $member, User $actor, string $type, UploadedFile $file): MemberDocument
    {
        $this->branchScope->assertAccessibleRecord($member->branch_id);
        $disk = 'local';
        $storedName = Str::uuid().'.'.$file->guessExtension();
        $path = $file->storeAs(
            "membership/{$member->organisation->public_id}/{$member->public_id}",
            $storedName,
            $disk,
        );

        if ($path === false) {
            throw new \RuntimeException('The member document could not be stored.');
        }

        try {
            return DB::transaction(fn (): MemberDocument => MemberDocument::query()->create([
                'organisation_id' => $member->organisation_id,
                'documentable_type' => 'member',
                'documentable_id' => $member->getKey(),
                'document_type' => $type,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'verification_status' => DocumentVerificationStatus::Pending,
                'uploaded_by_user_id' => $actor->getKey(),
            ]));
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
