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
            && (
                ! $this->exists('assigned_to_user_id')
                || $user->canManageDepartmentTickets()
                || (
                    $user->role === 'staff'
                    && $ticket->department_id === $user->department_id
                    && ($ticket->assigned_to_user_id === null || $ticket->assigned_to_user_id === $user->id)
                )
            );
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

        return [
            'status' => ['required', Rule::in(['New', 'Assigned', 'In Progress', 'Waiting for User', 'On Hold', 'Resolved', 'Closed'])],
            'priority' => ['sometimes', 'required', Rule::in(['Urgent', 'High', 'Medium', 'Low'])],
            'assigned_to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($ticket, $user): void {
                    $query
                        ->where('employment_status', 'active')
                        ->where('staff_approval_status', 'approved')
                        ->where(function ($query) use ($ticket, $user): void {
                            $query->where(function ($query) use ($ticket, $user): void {
                                if ($ticket?->department_id === null) {
                                    $query->whereRaw('1 = 0');
                                } else {
                                    $query
                                        ->whereIn('role', ['staff', 'department_admin'])
                                        ->where('department_id', $ticket->department_id);
                                }

                                if ($user?->role === 'staff' && $ticket?->assigned_to_user_id === null) {
                                    $query->where('id', $user->id);
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
