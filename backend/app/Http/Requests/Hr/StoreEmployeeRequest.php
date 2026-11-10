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
 * Validation for creating an HR employee record.
 *
 * `authorize()` delegates to EmployeePolicy::create so the permission check and
 * the rule set can never drift apart, and so the route middleware and the
 * controller agree on who may call this.
 *
 * A login account is optional, but when one is requested (an email is present)
 * it must carry both a username and an email, unique across the institution.
 */
class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    /**
     * Normalise before validation so comparisons (uniqueness, links) are made
     * against the canonical form the database stores.
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
        return [
            'employee_number' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('employees', 'employee_number'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],

            'email' => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')],
            'username' => [
                'nullable', 'required_with:email', 'string', 'max:80',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:191'],
            'account_status' => ['nullable', Rule::enum(UserStatus::class)],

            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:employees,id'],

            'employment_status' => ['nullable', Rule::in(HrDashboardService::EMPLOYMENT_STATUSES)],
            'employment_type' => ['nullable', 'string', 'max:20'],
            'date_hired' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
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
            'username.required_with' => 'Provide a username for the login account.',
            'username.regex' => 'The username may only contain letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
            'password.min' => 'The password must be at least 8 characters.',
            'department_id.required' => 'Select the department this employee belongs to.',
        ];
    }

    /**
     * An account needs a way to sign in; without an explicit password the
     * account is created without a usable credential, so say so up front rather
     * than leaving a half-working login behind.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('email') && ! $this->filled('password')) {
                $validator->errors()->add(
                    'password',
                    'Provide a password so the new account can sign in, or leave the email blank.',
                );
            }

            if ($this->filled('username') && ! $this->filled('email')) {
                $validator->errors()->add(
                    'email',
                    'Provide an email address so the login account can be created.',
                );
            }
        });
    }
}
