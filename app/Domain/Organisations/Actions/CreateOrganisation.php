<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Enums\OrganisationType;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Support\Str;

class CreateOrganisation
{
    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     organisation_type: OrganisationType,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     logo_path?: string|null,
     *     currency_code?: string,
     *     timezone?: string,
     *     status?: OrganisationStatus
     * }  $attributes
     */
    public function handle(array $attributes): Organisation
    {
        return Organisation::query()->create([
            ...$attributes,
            'public_id' => (string) Str::uuid7(),
            'code' => Str::upper($attributes['code']),
            'currency_code' => Str::upper(
                $attributes['currency_code']
                    ?? (string) config('cofund.organisations.default_currency_code'),
            ),
            'timezone' => $attributes['timezone']
                ?? (string) config('cofund.organisations.default_timezone'),
            'status' => $attributes['status'] ?? OrganisationStatus::Pending,
        ]);
    }
}
