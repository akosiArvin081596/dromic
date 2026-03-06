<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:local,massive'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city_municipality_ids' => ['nullable', 'array'],
            'city_municipality_ids.*' => ['required', 'exists:city_municipalities,id'],
        ];
    }
}
