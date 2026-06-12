<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('emailTemplate')?->id ?? $this->route('emailTemplate') ?? null;
        return [
            'key' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255', Rule::unique('email_templates', 'key')->ignore($ignoreId)],
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'subject' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'body' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
