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
        $slugRule = Rule::unique('email_templates', 'slug')->ignore($ignoreId);
        $keyRule = Rule::unique('email_templates', 'key')->ignore($ignoreId);
        $requiredKeyRule = $this->isMethod('post') ? 'required_without:slug' : 'sometimes';
        $requiredSlugRule = $this->isMethod('post') ? 'required_without:key' : 'sometimes';

        return [
            'key' => [$requiredKeyRule, 'string', 'max:255', $keyRule],
            'slug' => [$requiredSlugRule, 'string', 'max:255', $slugRule],
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'subject' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'body' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'is_active' => ['nullable', 'boolean'],
            'module' => ['nullable', 'string', 'max:255'],
            'event_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
