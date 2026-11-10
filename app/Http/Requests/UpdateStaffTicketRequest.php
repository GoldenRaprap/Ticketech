<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\Models\User;
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
        $user = $this->user();
        $ticket = $this->route('ticket');

        abort_unless(
            $user instanceof User
                && $ticket instanceof Ticket
                && $ticket->isVisibleTo($user),
            404,
        );

        return $user->isStaff()
            && (! $this->exists('assigned_to_user_id') || $user->canManageDepartmentTickets());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $user = $this->user();
        $assigneeId = $this->input('assigned_to_user_id', $ticket?->assigned_to_user_id);
        $willBeAssigned = filled($assigneeId);

        return [
            'status' => ['required', Rule::in(['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold', 'Resolved', 'Closed'])],
            'priority' => $willBeAssigned
                ? ['sometimes', 'required', Rule::in(['Urgent', 'High', 'Medium', 'Low'])]
                : ['prohibited'],
            'assigned_to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($ticket, $user): void {
                    $query
                        ->where('employment_status', 'active')
                        ->where('staff_approval_status', 'approved')
                        ->where(function ($query) use ($ticket, $user): void {
                            $query->where(function ($query) use ($ticket): void {
                                if ($ticket?->department_id === null) {
                                    $query->whereRaw('1 = 0');
                                } else {
                                    $query
                                        ->where('role', 'staff')
                                        ->where('department_id', $ticket->department_id);
                                }
                            });

                            if ($user?->isAdmin()) {
                                $query->orWhere('role', 'admin');
                            }
                        });
                }),
            ],
        ];
    }
}
