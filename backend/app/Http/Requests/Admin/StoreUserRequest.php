<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permissions;
use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for creating a user account from the People Management module.
 *
 * The linked-employee rule is structural: `employees.user_id` is unique in the
 * database, but an explicit 422 is far clearer than a duplicate-key failure,
 * and it lets the message say what actually went wrong.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::USERS_CREATE) ?? false;
    }

    /**
     * Normalise the identity fields before validating uniqueness.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('name'))) {
            $merge['name'] = Str::squish($this->input('name'));
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'username' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:191'],
            'role' => ['required', 'string', Rule::in(array_column(RoleEnum::cases(), 'value'))],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'That email address already belongs to an account.',
            'username.regex' => 'The username may only contain letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
            'employee_id.exists' => 'That employee record no longer exists.',
            'role.in' => 'Unknown role. Valid roles: admin, hr, department_head, employee.',
            'password.min' => 'Password must contain at least 8 characters.',
        ];
    }

    /**
     * An employee can hold exactly one login account. Say so explicitly and
     * point at the account that already owns it instead of failing on the
     * unique index.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employeeId = $this->input('employee_id');

            if (! filled($employeeId)) {
                return;
            }

            $employee = Employee::query()->find((int) $employeeId);

            if ($employee === null) {
                return;
            }

            if ($employee->user_id !== null) {
                $validator->errors()->add(
                    'employee_id',
                    'That employee already has a login account. Open the existing account instead.',
                );
            }
        });
    }
}
