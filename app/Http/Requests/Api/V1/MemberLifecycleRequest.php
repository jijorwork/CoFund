<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()->getActionMethod()) {
            'suspend', 'reinstate', 'markInactive' => FoundationPermission::MembersSuspend,
            'activate' => FoundationPermission::MembersActivate,
            'exit', 'markDeceased' => FoundationPermission::MembersExit,
            default => FoundationPermission::MembersUpdate,
        };

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->route()->getActionMethod() === 'suspend'),
                'nullable', 'string', 'max:2000',
            ],
            'effective_date' => [
                Rule::requiredIf(fn (): bool => in_array(
                    $this->route()->getActionMethod(),
                    ['exit', 'markDeceased'],
                    true,
                )),
                'nullable', 'date',
            ],
        ];
    }
}
