<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialReferenceType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostFinancialTransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::TransactionsPost->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'branch_uuid' => ['sometimes', 'nullable', 'uuid'],
            'transaction_type' => ['required', Rule::in([
                FinancialTransactionType::Adjustment->value,
                FinancialTransactionType::WalletTransfer->value,
            ])],
            'currency_code' => ['required', 'string', 'size:3'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'external_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference_type' => ['sometimes', 'nullable', Rule::enum(FinancialReferenceType::class)],
            'reference_uuid' => ['sometimes', 'nullable', 'uuid'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*' => ['required', 'array:account_uuid,entry_type,amount_minor,description,member_uuid,wallet_uuid'],
            'entries.*.account_uuid' => ['required', 'uuid'],
            'entries.*.entry_type' => ['required', Rule::enum(EntryType::class)],
            'entries.*.amount_minor' => ['required', 'integer', 'min:1'],
            'entries.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'entries.*.member_uuid' => ['sometimes', 'nullable', 'uuid'],
            'entries.*.wallet_uuid' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
