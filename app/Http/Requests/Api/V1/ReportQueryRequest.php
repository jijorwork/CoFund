<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $context = app(TenantContext::class);
        $organisationId = $context->organisation()->getKey();

        return [
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'branch_uuid' => [
                'nullable', 'uuid',
                Rule::exists('branches', 'public_id')->where('organisation_id', $organisationId),
            ],
            'group_uuid' => [
                'nullable', 'uuid',
                Rule::exists('groups', 'public_id')->where('organisation_id', $organisationId),
            ],
            'member_uuid' => [
                'nullable', 'uuid',
                Rule::exists('members', 'public_id')->where('organisation_id', $organisationId),
            ],
            'product_uuid' => ['nullable', 'uuid'],
            'wallet_uuid' => [
                'nullable', 'uuid',
                Rule::exists('wallets', 'public_id')->where('organisation_id', $organisationId),
            ],
            'ledger_account_uuid' => [
                'nullable', 'uuid',
                Rule::exists('ledger_accounts', 'public_id')->where('organisation_id', $organisationId),
            ],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'max:50'],
            'comparison' => ['nullable', Rule::in(['previous_period', 'previous_month', 'previous_quarter', 'previous_year'])],
            'include_zero_balances' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
