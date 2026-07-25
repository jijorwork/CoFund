<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContributionEnrolmentLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()->getActionMethod()) {
            'approve', 'reject' => FoundationPermission::ContributionEnrolmentsApprove,
            'suspend', 'reinstate' => FoundationPermission::ContributionEnrolmentsSuspend,
            'end', 'cancel' => FoundationPermission::ContributionEnrolmentsEnd,
            default => FoundationPermission::ContributionEnrolmentsView,
        };

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();

        return [
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array($action, ['suspend', 'end'], true)),
                'nullable', 'string', 'max:2000',
            ],
            'effective_date' => [
                Rule::requiredIf(fn (): bool => $action === 'end'),
                'nullable', 'date',
            ],
        ];
    }
}
