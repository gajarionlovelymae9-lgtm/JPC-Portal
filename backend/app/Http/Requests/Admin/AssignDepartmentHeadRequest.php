<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assign a department head from the People Management module.
 *
 * The employee must exist and be active; whether they really belong to the
 * department and hold the right role is validated by the service because it
 * needs the department context.
 */
class AssignDepartmentHeadRequest extends FormRequest
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
        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
