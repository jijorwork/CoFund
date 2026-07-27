<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Services\NotificationEventRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(NotificationCategory::class)],
            'event_key' => ['nullable', 'string', 'max:100'],
            'in_app_enabled' => ['nullable', 'boolean'],
            'email_enabled' => ['nullable', 'boolean'],
            'sms_enabled' => ['nullable', 'boolean'],
            'messaging_enabled' => ['nullable', 'boolean'],
            'digest_frequency' => ['nullable', Rule::in(['immediate', 'hourly', 'daily', 'weekly'])],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
            'locale' => ['nullable', 'string', 'max:12'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $eventKey = $this->input('event_key');
                if (is_string($eventKey) && ! app(NotificationEventRegistry::class)->has($eventKey)) {
                    $validator->errors()->add('event_key', 'The selected notification event is invalid.');
                }
            },
        ];
    }
}
