<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
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
        $tag = $this->route('tag');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('tags', 'name')->ignore($tag?->id),
            ],
            'is_testing' => ['sometimes', 'boolean'],
        ];
    }
}
