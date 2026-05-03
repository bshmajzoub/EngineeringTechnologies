<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_ids' => ['nullable', 'array'],
            // Each ID must belong to an active employee — admin IDs and
            // inactive users are rejected at the validation layer.
            'employee_ids.*' => [
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', 'employee')
                    ->where('is_active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.*.exists' => 'One or more selected IDs do not belong to active employees.',
        ];
    }
}
