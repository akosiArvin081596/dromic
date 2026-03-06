<?php

namespace App\Http\Requests;

use App\Enums\IncidentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'category' => ['required', new Enum(IncidentCategory::class)],
            'identifier' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:local,massive'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:active,closed'],
            'city_municipality_ids' => ['nullable', 'array'],
            'city_municipality_ids.*' => ['required', 'exists:city_municipalities,id'],
        ];
    }
}
