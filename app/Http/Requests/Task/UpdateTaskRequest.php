<?php

namespace App\Http\Requests\Task;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'task_date' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_at' => ['sometimes', 'required', 'date', 'after_or_equal:now'],
            'end_at' => ['sometimes', 'nullable', 'date'],
            'assignment_notes' => ['sometimes', 'nullable', 'array'],
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

                $task = $this->route('task');
                $startAt = $this->input('start_at', $task?->start_at);
                $endAt = $this->input('end_at', $task?->end_at);

                if ($startAt && $endAt && Carbon::parse($endAt)->lte(Carbon::parse($startAt))) {
                    $validator->errors()->add('end_at', 'The end at field must be after start at.');
                }
            },
        ];
    }
}
