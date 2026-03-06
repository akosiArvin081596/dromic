<?php

namespace App\Http\Requests;

use App\Enums\AugmentationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequestLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $this->user()->can('create', [\App\Models\RequestLetter::class, $incident]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'augmentation_items' => ['required', 'array', 'min:1'],
            'augmentation_items.*.type' => ['required', 'string', Rule::in(array_column(AugmentationType::cases(), 'value'))],
            'augmentation_items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateNoDuplicateTypes($validator);
            $this->validateQuantityAgainstAffectedFamilies($validator);
        });
    }

    private function validateNoDuplicateTypes(Validator $validator): void
    {
        $items = $this->input('augmentation_items', []);
        $types = array_column($items, 'type');
        $duplicates = array_diff_assoc($types, array_unique($types));

        foreach ($duplicates as $index => $type) {
            $validator->errors()->add(
                "augmentation_items.{$index}.type",
                'Duplicate augmentation type is not allowed.'
            );
        }
    }

    private function validateQuantityAgainstAffectedFamilies(Validator $validator): void
    {
        $incident = $this->route('incident');
        $user = $this->user();

        $latestReport = $incident->reports()
            ->where('city_municipality_id', $user->city_municipality_id)
            ->orderByDesc('sequence_number')
            ->first();

        if (! $latestReport) {
            $validator->errors()->add('file', 'You must have a DROMIC report before uploading a request letter.');

            return;
        }

        $affectedFamilies = collect($latestReport->affected_areas ?? [])
            ->sum(fn (array $row): int => (int) ($row['families'] ?? 0));

        foreach ($this->input('augmentation_items', []) as $index => $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity > $affectedFamilies) {
                $label = AugmentationType::tryFrom($item['type'] ?? '')?->label() ?? $item['type'] ?? '';
                $validator->errors()->add(
                    "augmentation_items.{$index}.quantity",
                    "The quantity for {$label} ({$quantity}) cannot exceed the total affected families ({$affectedFamilies})."
                );
            }
        }
    }
}
