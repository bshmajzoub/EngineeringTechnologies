<?php

namespace App\Http\Requests\Employee;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkDeleteEmployeesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $employeeIds = collect($this->input('employee_ids', []))
                    ->map(fn (mixed $employeeId): int => (int) $employeeId)
                    ->unique()
                    ->values();

                $employeesCount = User::query()
                    ->whereIn('id', $employeeIds)
                    ->where('role', UserRole::Employee->value)
                    ->count();

                if ($employeesCount !== $employeeIds->count()) {
                    $validator->errors()->add('employee_ids', 'All selected users must be employees.');
                }
            },
        ];
    }
}
