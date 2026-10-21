<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold', 'Resolved', 'Closed'])],
            'priority' => ['required', Rule::in(['Urgent', 'High', 'Medium', 'Low'])],
            'assigned_to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', ['staff', 'admin'])
                    ->where('employment_status', 'active')),
            ],
        ];
    }
}
