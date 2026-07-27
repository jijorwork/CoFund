<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadExpenseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['document_type' => ['required', 'in:expense_request,quotation,invoice,receipt,payment_evidence,petty_cash_retirement,approval_evidence,other'], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']];
    }
}
