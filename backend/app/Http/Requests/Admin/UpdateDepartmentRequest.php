<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permissions;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a department from the People Management module.
 *
 * Uniqueness ignores the department being edited. requests look up the route
 * parameter, accepting either the bound model or the raw id depending on how
 * Laravel resolved `admin/departments/{department}`.
 */
class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::DEPARTMENTS_UPDATE) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('department');
        $id = $id instanceof Department ? $id->id : $id;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255', Rule::unique('departments', 'name')->ignore($id)],
            'code' => ['sometimes', 'nullable', 'string', 'max:20', 'alpha_dash', Rule::unique('departments', 'code')->ignore($id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
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
