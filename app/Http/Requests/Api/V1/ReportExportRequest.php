<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\Enums\ReportExportFormat;
use App\Domain\Reports\Services\ReportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'report_key' => ['required', 'string', 'max:100'],
            'format' => ['required', Rule::enum(ReportExportFormat::class)],
            'filters' => ['present', 'array'],
            'columns' => ['nullable', 'array'],
            'sorting' => ['nullable', 'array'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $registry = app(ReportRegistry::class);
                $key = $this->input('report_key');
                if (! is_string($key) || ! $registry->has($key)) {
                    $validator->errors()->add('report_key', 'The selected report is not registered.');

                    return;
                }
                $definition = $registry->get($key);
                if (! in_array((string) $this->input('format'), $definition->exportFormats, true)) {
                    $validator->errors()->add('format', 'The selected format is not supported by this report.');
                }
                foreach (array_keys((array) $this->input('filters', [])) as $filter) {
                    if (! in_array($filter, $definition->filters, true)) {
                        $validator->errors()->add('filters.'.$filter, 'This filter is not supported by the report.');
                    }
                }
            },
        ];
    }
}
