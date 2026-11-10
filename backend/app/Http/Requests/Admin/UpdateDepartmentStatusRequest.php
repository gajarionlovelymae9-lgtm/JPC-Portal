<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Activate or deactivate a department from the People Management module.
 *
 * Deactivation is the only removal path on purpose: a department carrying
 * employees keeps its members and history intact.
 */
class UpdateDepartmentStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
