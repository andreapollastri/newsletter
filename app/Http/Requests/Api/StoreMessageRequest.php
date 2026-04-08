<?php

namespace App\Http\Requests\Api;

use App\Enums\MessageStatus;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMessageRequest extends FormRequest
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
        return [
            'template_id' => ['required', 'uuid', 'exists:templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'html_content' => ['required', 'string'],
            'status' => ['required', Rule::enum(MessageStatus::class)],
            'scheduled_at' => ['nullable', 'date'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['uuid', 'exists:tags,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('status');
            if ($status instanceof MessageStatus) {
                $status = $status->value;
            }
            if (in_array($status, [MessageStatus::Sending->value, MessageStatus::Sent->value], true)) {
                $validator->errors()->add('status', __('Creating messages in :status status is not allowed via API.', [
                    'status' => $status,
                ]));
            }

            $tagIds = $this->input('tag_ids', []);
            if (! is_array($tagIds) || $tagIds === []) {
                return;
            }

            $tags = Tag::query()->whereIn('id', $tagIds)->get();
            if ($tags->isEmpty()) {
                return;
            }

            $hasTesting = $tags->contains(fn (Tag $tag): bool => $tag->is_testing);
            $hasNonTesting = $tags->contains(fn (Tag $tag): bool => ! $tag->is_testing);
            if ($hasTesting && $hasNonTesting) {
                $validator->errors()->add('tag_ids', __('You cannot mix testing tags with normal tags on the same message.'));
            }
        });
    }
}
