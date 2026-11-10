<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class HrAchievementRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Checked against the role permission registry (same source as the
        // permission middleware), not the empty Laravel Gate.
        return $user !== null && $user->hasPermission('achievements.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
