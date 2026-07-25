<?php

namespace App\Domain\Organisations\Services;

use App\Domain\Organisations\Enums\SettingValueType;
use App\Domain\Organisations\Models\OrganisationSetting;
use App\Domain\Organisations\Tenancy\TenantContext;
use JsonException;

class OrganisationSettingReader
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @throws JsonException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = OrganisationSetting::query()
            ->whereBelongsTo($this->tenantContext->organisation())
            ->where('key', $key)
            ->first();

        if ($setting === null || $setting->value === null) {
            return $default;
        }

        return match ($setting->value_type) {
            SettingValueType::String => $setting->value,
            SettingValueType::Integer => (int) $setting->value,
            SettingValueType::Boolean => filter_var($setting->value, FILTER_VALIDATE_BOOL),
            SettingValueType::Decimal => $setting->value,
            SettingValueType::Json => json_decode(
                $setting->value,
                true,
                flags: JSON_THROW_ON_ERROR,
            ),
        };
    }
}
