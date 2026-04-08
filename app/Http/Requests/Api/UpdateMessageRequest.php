<?php

namespace App\Http\Requests\Api;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMessageRequest extends FormRequest
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
            'template_id' => ['sometimes', 'uuid', 'exists:templates,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'html_content' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(MessageStatus::class)],
            'scheduled_at' => ['nullable', 'date'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['uuid', 'exists:tags,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Message|null $message */
            $message = $this->route('message');
            if (! $message instanceof Message) {
                return;
            }

            if ($message->status === MessageStatus::Sent || $message->status === MessageStatus::Sending) {
                if ($this->hasAny(['template_id', 'subject', 'html_content', 'status', 'scheduled_at', 'tag_ids'])) {
                    $validator->errors()->add('status', __('Sent or sending messages cannot be modified via API.'));
                }
            }

            if ($message->status === MessageStatus::Sending && $this->has('status')) {
                $validator->errors()->add('status', __('Cannot change status while the message is sending.'));
            }

            $tagIds = $this->input('tag_ids', null);
            if ($tagIds === null) {
                return;
            }

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
