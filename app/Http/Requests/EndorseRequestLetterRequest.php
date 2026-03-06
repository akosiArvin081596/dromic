<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EndorseRequestLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('endorse', $this->route('requestLetter'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'augmentation_items' => ['required', 'array', 'min:1'],
            'augmentation_items.*.type' => ['required', 'string'],
            'augmentation_items.*.endorsed_quantity' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $requestLetter = $this->route('requestLetter');
            $originalItems = collect($requestLetter->augmentation_items)->keyBy('type');

            foreach ($this->input('augmentation_items', []) as $index => $item) {
                $type = $item['type'] ?? '';
                $endorsed = (int) ($item['endorsed_quantity'] ?? 0);
                $original = $originalItems->get($type);

                if (! $original) {
                    $validator->errors()->add("augmentation_items.{$index}.type", 'Invalid augmentation type.');

                    continue;
                }

                if ($endorsed > (int) $original['quantity']) {
                    $validator->errors()->add(
                        "augmentation_items.{$index}.endorsed_quantity",
                        "Endorsed quantity ({$endorsed}) cannot exceed requested quantity ({$original['quantity']})."
                    );
                }
            }
        });
    }
}
