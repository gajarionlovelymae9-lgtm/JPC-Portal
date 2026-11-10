<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permissions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reset an account password from the People Management module.
 *
 * The new password is supplied by the operator and only ever stored hashed;
 * it is never echoed back in a response.
 */
class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permissions::USERS_UPDATE) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'max:191'],
        ];
    }
}
