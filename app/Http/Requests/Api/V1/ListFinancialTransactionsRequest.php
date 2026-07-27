<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFinancialTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::TransactionsView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:transaction_number,transaction_type,status,branch_uuid,member_uuid,wallet_uuid,account_uuid,transaction_date_from,transaction_date_to,external_reference'],
            'filter.transaction_number' => ['sometimes', 'string', 'max:100'],
            'filter.transaction_type' => ['sometimes', Rule::enum(FinancialTransactionType::class)],
            'filter.status' => ['sometimes', Rule::enum(FinancialTransactionStatus::class)],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.member_uuid' => ['sometimes', 'uuid'],
            'filter.wallet_uuid' => ['sometimes', 'uuid'],
            'filter.account_uuid' => ['sometimes', 'uuid'],
            'filter.transaction_date_from' => ['sometimes', 'date'],
            'filter.transaction_date_to' => ['sometimes', 'date', 'after_or_equal:filter.transaction_date_from'],
            'filter.external_reference' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', Rule::in(['transaction_date', '-transaction_date', 'transaction_number', '-transaction_number', 'posted_at', '-posted_at', 'created_at', '-created_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
