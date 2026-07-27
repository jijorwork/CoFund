<?php

namespace App\Domain\Reports\Models;

use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Reports\Enums\SavedReportVisibility;
use App\Models\User;
use Database\Factories\Domain\Reports\Models\SavedReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $organisation_id
 * @property int $owner_user_id
 * @property int|null $acting_role_id
 * @property string $public_id
 * @property string $name
 * @property string|null $description
 * @property string $report_key
 * @property array<string, mixed> $filters
 * @property array<string, mixed>|null $grouping
 * @property array<string, mixed>|null $sorting
 * @property list<string>|null $columns
 * @property SavedReportVisibility $visibility
 * @property bool $is_favourite
 * @property bool $is_default
 */
#[Fillable(['public_id', 'organisation_id', 'owner_user_id', 'acting_role_id', 'name', 'description', 'report_key', 'filters', 'grouping', 'sorting', 'columns', 'visibility', 'is_favourite', 'is_default'])]
class SavedReport extends Model
{
    /** @use HasFactory<SavedReportFactory> */
    use HasFactory, HasUuids, SoftDeletes;

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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
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
            'filters' => 'array',
            'grouping' => 'array',
            'sorting' => 'array',
            'columns' => 'array',
            'visibility' => SavedReportVisibility::class,
            'is_favourite' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
