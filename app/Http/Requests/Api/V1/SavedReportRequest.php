<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\Enums\SavedReportVisibility;
use App\Domain\Reports\Services\ReportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'report_key' => [$required, 'string', 'max:100'],
            'filters' => [$required, 'array'],
            'grouping' => ['nullable', 'array'],
            'sorting' => ['nullable', 'array'],
            'columns' => ['nullable', 'array'],
            'visibility' => [$required, Rule::enum(SavedReportVisibility::class)],
            'is_favourite' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $registry = app(ReportRegistry::class);
                $key = $this->input('report_key');
                if (is_string($key) && ! $registry->has($key)) {
                    $validator->errors()->add('report_key', 'The selected report is not registered.');

                    return;
                }
                if (! is_string($key) || ! $registry->has($key)) {
                    return;
                }
                $definition = $registry->get($key);
                foreach (array_keys((array) $this->input('filters', [])) as $filter) {
                    if (! in_array($filter, $definition->filters, true)) {
                        $validator->errors()->add('filters.'.$filter, 'This filter is not supported by the report.');
                    }
                }
            },
        ];
    }
}
