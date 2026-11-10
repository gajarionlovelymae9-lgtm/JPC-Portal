<?php

namespace App\Http\Requests\Hr;

use App\Enums\UserStatus;
use App\Models\Employee;
use App\Services\Hr\HrDashboardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for editing an HR employee record.
 *
 * Every field is `sometimes`/`nullable` so a partial edit is a real partial
 * edit: the service diffs against the stored record and only writes what the
 * caller actually sent, which is what keeps `employee_histories` meaningful.
 */
class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        if (! $employee instanceof Employee) {
            return false;
        }

        return $this->user()?->can('update', $employee) ?? false;
    }

    /**
     * Normalise before validation so a case/spacing-only edit is not treated as
     * a real change and uniqueness checks use the stored canonical form.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = Str::squish($this->input($field));
            }
        }

        foreach (['email', 'username'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = Str::lower(trim($this->input($field)));
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $id = $employee instanceof Employee ? $employee->id : null;

        return [
            'employee_number' => [
                'sometimes', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('employees', 'employee_number')->ignore($id),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'suffix' => ['sometimes', 'nullable', 'string', 'max:20'],

            'email' => ['sometimes', 'nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->accountId())],
            'username' => [
                'sometimes', 'nullable', 'string', 'max:80',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->accountId()),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:191'],
            'account_status' => ['sometimes', 'nullable', Rule::enum(UserStatus::class)],

            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'nullable', 'integer', 'exists:positions,id'],
            'supervisor_id' => [
                'sometimes', 'nullable', 'integer', 'exists:employees,id',
                // An employee cannot supervise themselves.
                Rule::notIn([$id]),
            ],

            'employment_status' => ['sometimes', 'nullable', Rule::in(HrDashboardService::EMPLOYMENT_STATUSES)],
            'employment_type' => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_hired' => ['sometimes', 'nullable', 'date'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],

            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.regex' => 'The employee ID may only contain letters, numbers and dashes.',
            'employee_number.unique' => 'That employee ID is already in use.',
            'email.unique' => 'That email address already belongs to an account.',
            'username.regex' => 'The username may only contain letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
            'password.min' => 'The password must be at least 8 characters.',
            'supervisor_id.not_in' => 'An employee cannot be their own supervisor.',
        ];
    }

    /**
     * A username is only meaningful with an account; refuse to store one for an
     * employee who has no account and supplied no email to create it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employee = $this->route('employee');

            if (
                $this->filled('username')
                && ! $employee?->user_id
                && ! $this->filled('email')
            ) {
                $validator->errors()->add(
                    'email',
                    'Provide an email address so the login account can be created.',
                );
            }
        });
    }

    /**
     * The id to ignore when checking email/username uniqueness, so saving an
     * employee without touching either does not collide with their own account.
     */
    private function accountId(): ?int
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $employee->user_id : null;
    }
}
