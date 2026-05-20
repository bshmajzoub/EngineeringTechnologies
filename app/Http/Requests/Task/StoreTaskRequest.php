<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'required', Rule::in(TaskPriority::values())],
            'reminder_interval_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'task_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_at' => ['required', 'date', 'after_or_equal:now'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'assignment_notes' => ['nullable', 'array'],
            'assignment_notes.*' => ['nullable', 'string'],
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

                $activeEmployeeCount = User::query()
                    ->whereIn('id', $employeeIds)
                    ->where('role', UserRole::Employee->value)
                    ->where('is_active', true)
                    ->count();

                if ($activeEmployeeCount !== $employeeIds->count()) {
                    $validator->errors()->add('employee_ids', 'All selected users must be active employees.');
                }
            },
        ];
    }
}
