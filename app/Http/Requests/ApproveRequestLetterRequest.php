<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveRequestLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('requestLetter'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'augmentation_items' => ['required', 'array', 'min:1'],
            'augmentation_items.*.type' => ['required', 'string'],
            'augmentation_items.*.approved_quantity' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $requestLetter = $this->route('requestLetter');
            $originalItems = collect($requestLetter->augmentation_items)->keyBy('type');

            foreach ($this->input('augmentation_items', []) as $index => $item) {
                $type = $item['type'] ?? '';
                $approved = (int) ($item['approved_quantity'] ?? 0);
                $original = $originalItems->get($type);

                if (! $original) {
                    $validator->errors()->add("augmentation_items.{$index}.type", 'Invalid augmentation type.');

                    continue;
                }

                $endorsed = (int) ($original['endorsed_quantity'] ?? $original['quantity']);

                if ($approved > $endorsed) {
                    $validator->errors()->add(
                        "augmentation_items.{$index}.approved_quantity",
                        "Approved quantity ({$approved}) cannot exceed endorsed quantity ({$endorsed})."
                    );
                }
            }
        });
    }
}
