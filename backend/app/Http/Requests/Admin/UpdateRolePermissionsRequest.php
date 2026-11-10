<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Replace a role's permission set from the Roles & Access tab.
 *
 * The elevated (admin-authority) deny-list is enforced by the service; this
 * request only validates the shape of the payload.
 */
class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::PERMISSIONS_MANAGE) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'required',
                'string',
                Rule::exists('permissions', 'name'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.*.exists' => 'Unknown permission name.',
        ];
    }

    /**
     * Deduplicate before validation so the service sees a clean list.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $data = parent::validationData();

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $data['permissions'] = array_values(array_unique($data['permissions']));
        }

        return $data;
    }
}
