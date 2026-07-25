<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Contributions\Enums\ContributionAmountType;
use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Enums\ContributionPlanType;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContributionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->isMethod('post')
            ? FoundationPermission::ContributionPlansCreate
            : FoundationPermission::ContributionPlansUpdate;

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'branch_uuid' => ['sometimes', 'nullable', 'uuid'],
            'group_uuid' => ['sometimes', 'nullable', 'uuid'],
            'name' => [$required, 'string', 'max:255'],
            'code' => [$required, 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'plan_type' => [$required, Rule::enum(ContributionPlanType::class)],
            'frequency' => [$required, Rule::enum(ContributionFrequency::class)],
            'amount_type' => [$required, Rule::enum(ContributionAmountType::class)],
            'fixed_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'minimum_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'maximum_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'collection_day_of_week' => ['sometimes', 'nullable', 'integer', 'between:1,7'],
            'collection_day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,28'],
            'grace_period_days' => ['sometimes', 'integer', 'between:0,365'],
            'allow_partial_payments' => ['sometimes', 'boolean'],
            'allow_advance_payments' => ['sometimes', 'boolean'],
            'allow_overpayments' => ['sometimes', 'boolean'],
            'requires_approval_to_join' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->input('amount_type');
                $frequency = $this->input('frequency');

                if ($type === ContributionAmountType::Fixed->value && ! $this->filled('fixed_amount_minor')) {
                    $validator->errors()->add('fixed_amount_minor', 'A fixed amount is required.');
                }

                if ($type === ContributionAmountType::MemberSelected->value && ! $this->filled('minimum_amount_minor')) {
                    $validator->errors()->add('minimum_amount_minor', 'A minimum amount is required.');
                }

                if ($type !== ContributionAmountType::Fixed->value && $this->filled('fixed_amount_minor')) {
                    $validator->errors()->add('fixed_amount_minor', 'Fixed amount is not allowed for this amount type.');
                }

                if ($type === ContributionAmountType::Flexible->value && $frequency !== ContributionFrequency::Flexible->value) {
                    $validator->errors()->add('frequency', 'Flexible amounts require flexible frequency.');
                }

                if ($frequency === ContributionFrequency::OneTime->value && ! $this->filled('start_date')) {
                    $validator->errors()->add('start_date', 'A one-time plan requires a start date.');
                }
            },
        ];
    }
}
