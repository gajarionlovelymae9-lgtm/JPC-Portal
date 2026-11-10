<?php

namespace App\Http\Requests\Escalations;

use App\Enums\EscalationPriority;
use App\Enums\EscalationType;
use App\Models\DepartmentEscalation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a Department Head raising a new escalation.
 *
 * Cross-department ownership of the employee, related record and assignee is
 * enforced in the service layer; the rules here only guarantee the shape and
 * existence of the referenced records.
 */
class StoreEscalationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['department_head', 'admin']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(EscalationType::values())],
            'reason' => ['required', 'string', 'max:5000'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'related_type' => ['nullable', Rule::in(array_keys(DepartmentEscalation::RELATED_TYPES))],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'priority' => ['required', Rule::in(EscalationPriority::values())],
            'requested_action' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ];
    }
}
