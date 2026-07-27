<?php

namespace App\Domain\Reports\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reports\Enums\ReportExportFormat;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Models\User;
use Database\Factories\Domain\Reports\Models\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $organisation_id
 * @property int $requested_by_user_id
 * @property int|null $acting_role_id
 * @property string $public_id
 * @property string $report_key
 * @property ReportExportFormat $format
 * @property array<string, mixed> $filters
 * @property list<string>|null $columns
 * @property array<string, mixed>|null $sorting
 * @property ReportExportStatus $status
 * @property string|null $file_name
 * @property string|null $storage_path
 * @property int|null $row_count
 * @property string|null $currency_code
 * @property string|null $failure_reason
 * @property Carbon $requested_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['public_id', 'organisation_id', 'requested_by_user_id', 'acting_role_id', 'report_key', 'format', 'filters', 'columns', 'sorting', 'status', 'file_name', 'storage_path', 'row_count', 'currency_code', 'requested_at', 'started_at', 'completed_at', 'failed_at', 'expires_at', 'failure_reason'])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
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
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function actingRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'acting_role_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => ReportExportFormat::class,
            'status' => ReportExportStatus::class,
            'filters' => 'array',
            'columns' => 'array',
            'sorting' => 'array',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
