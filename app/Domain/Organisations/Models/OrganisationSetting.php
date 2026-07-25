<?php

namespace App\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\SettingValueType;
use Database\Factories\Domain\Organisations\Models\OrganisationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $key
 * @property string|null $value
 * @property SettingValueType $value_type
 * @property bool $is_public
 */
#[Fillable([
    'organisation_id',
    'key',
    'value',
    'value_type',
    'is_public',
])]
class OrganisationSetting extends Model
{
    /** @use HasFactory<OrganisationSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'value_type' => 'string',
        'is_public' => false,
    ];

    protected static function newFactory(): OrganisationSettingFactory
    {
        return OrganisationSettingFactory::new();
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_type' => SettingValueType::class,
            'is_public' => 'boolean',
        ];
    }
}
