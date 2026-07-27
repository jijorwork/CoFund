<?php

namespace App\Domain\Reports\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reports\Enums\ReportRunStatus;
use App\Models\User;
use Database\Factories\Domain\Reports\Models\ReportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $organisation_id
 * @property int $user_id
 * @property int|null $acting_role_id
 * @property int|null $saved_report_id
 * @property string $public_id
 * @property string $report_key
 * @property array<string, mixed> $filters
 * @property ReportRunStatus $status
 * @property int|null $row_count
 * @property int|null $duration_ms
 */
#[Fillable(['public_id', 'organisation_id', 'user_id', 'acting_role_id', 'saved_report_id', 'report_key', 'filters', 'status', 'row_count', 'duration_ms', 'started_at', 'completed_at', 'failed_at', 'failure_reason'])]
class ReportRun extends Model
{
    /** @use HasFactory<ReportRunFactory> */
    use HasFactory, HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function actingRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'acting_role_id');
    }

    /** @return BelongsTo<SavedReport, $this> */
    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'status' => ReportRunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
