<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a department from the People Management module.
 *
 * `authorize()` reads the caller's real permission instead of trusting the
 * route middleware alone, so the rule set and the authorisation can never
 * drift apart. Names and codes are unique at database level already; the
 * validation mirrors that so operators get a clean 422 instead of a 500.
 */
class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DEPARTMENTS_CREATE) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255', Rule::unique('departments', 'name')],
            'code' => ['nullable', 'string', 'max:20', 'alpha_dash', Rule::unique('departments', 'code')],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a department name.',
            'name.min' => 'The department name must be at least 2 characters.',
            'name.max' => 'The department name may not be longer than 255 characters.',
            'name.unique' => 'A department with this name already exists.',
            'code.max' => 'The department code may not be longer than 20 characters.',
            'code.unique' => 'A department with this code already exists.',
            'code.alpha_dash' => 'The department code may only contain letters, numbers, dashes and underscores.',
        ];
    }
}
