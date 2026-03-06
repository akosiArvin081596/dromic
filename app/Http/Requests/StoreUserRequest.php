<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->value === 'admin';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,regional,provincial,lgu,escort,regional_director'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'province_id' => ['nullable', 'exists:provinces,id'],
            'city_municipality_id' => ['nullable', 'exists:city_municipalities,id'],
        ];
    }
}
