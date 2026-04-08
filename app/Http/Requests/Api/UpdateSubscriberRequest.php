<?php

namespace App\Http\Requests\Api;

use App\Enums\SubscriberStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriberRequest extends FormRequest
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
        $subscriber = $this->route('subscriber');

        return [
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('subscribers', 'email')->ignore($subscriber?->id),
            ],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(SubscriberStatus::class)],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['uuid', 'exists:tags,id'],
        ];
    }
}
