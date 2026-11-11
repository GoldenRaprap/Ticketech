<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferStaffTicketRequest extends FormRequest
{
    public const DEFAULT_HANDOFF_NOTE = 'This ticket is being routed to your department for review and appropriate follow-up. Please review the request and take the next appropriate action.';

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
            && $user->hasPermissionTo(UserPermission::ManageDepartmentTickets)
            && ! in_array($ticket->status, ['Resolved', 'Closed'], true);
    }

    protected function prepareForValidation(): void
    {
        $handoffNote = $this->input('handoff_note');
        $ticket = $this->route('ticket');

        if (
            $ticket instanceof Ticket
            && $ticket->department_id !== null
            && ($handoffNote === null || (is_string($handoffNote) && trim($handoffNote) === ''))
        ) {
            $this->merge(['handoff_note' => self::DEFAULT_HANDOFF_NOTE]);
        }
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');

        return [
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) use ($ticket): void {
                    $query->where('is_active', true);

                    if ($ticket instanceof Ticket && $ticket->department_id !== null) {
                        $query->where('id', '<>', $ticket->department_id);
                    }
                }),
            ],
            'handoff_note' => $ticket instanceof Ticket && $ticket->department_id === null
                ? ['prohibited']
                : ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.exists' => "Select an active department other than the ticket's current department.",
        ];
    }
}
