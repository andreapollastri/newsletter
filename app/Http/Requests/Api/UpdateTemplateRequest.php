<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $template = $this->route('template');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('templates', 'name')->ignore($template?->id),
            ],
            'html_content' => ['sometimes', 'string'],
            'placeholders' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
