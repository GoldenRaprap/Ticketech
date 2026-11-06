<?php

namespace App\Services;

use App\Models\Ticket;

class RequesterTicketDocument
{
    /**
     * @return array{
     *     ticket_number: string,
     *     subject: string,
     *     submitted_at: string,
     *     detail_groups: list<array{title: string, details: list<array{label: string, value: string}>}>,
     *     messages: list<array{
     *         author: string,
     *         context: string,
     *         created_at: string,
     *         body: string,
     *         attachments: list<string>
     *     }>
     * }
     */
    public function forRequester(Ticket $ticket): array
    {
        $ticket->loadMissing(['department', 'category', 'assignedTo']);
        $timezone = config('app.display_timezone');
        $submittedAt = $ticket->created_at->copy()->timezone($timezone);

        $messages = [[
            'author' => $ticket->requester_name,
            'context' => 'Original request',
            'created_at' => $submittedAt->format('M j, Y g:i A'),
            'body' => $ticket->description,
            'attachments' => $ticket->attachments()
                ->whereNull('comment_id')
                ->orderBy('id')
                ->pluck('original_name')
                ->all(),
        ]];

        foreach (
            $ticket->comments()
                ->where('is_internal', false)
                ->with('attachments:id,comment_id,original_name')
                ->oldest()
                ->get() as $comment
        ) {
            $isRequesterComment = $comment->author_id === null
                && $comment->author_name === $ticket->requester_name;

            $messages[] = [
                'author' => $isRequesterComment ? 'You' : 'PCU IT Support',
                'context' => $isRequesterComment ? 'Your reply' : 'Support team',
                'created_at' => $comment->created_at->copy()->timezone($timezone)->format('M j, Y g:i A'),
                'body' => $comment->body,
                'attachments' => $comment->attachments->pluck('original_name')->all(),
            ];
        }

        $detailGroups = [
            [
                'title' => 'Ticket details',
                'details' => [
                    ['label' => 'Current status', 'value' => $ticket->status],
                    ['label' => 'Ticket number', 'value' => $ticket->ticket_number],
                    ['label' => 'Department', 'value' => $ticket->department?->name ?? 'Not available'],
                    ['label' => 'Category', 'value' => $ticket->category?->name ?? 'Not selected'],
                    ['label' => 'Assigned to', 'value' => $ticket->assignedTo?->name ?? 'Not assigned'],
                ],
            ],
            [
                'title' => 'Client information',
                'details' => [
                    ['label' => 'Requester', 'value' => $ticket->requester_name],
                    ['label' => 'Student or employee ID', 'value' => $ticket->student_id],
                    ['label' => 'Email', 'value' => $ticket->requester_email],
                    ['label' => 'Phone', 'value' => $ticket->requester_phone ?: 'Not provided'],
                ],
            ],
        ];

        return [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'submitted_at' => $submittedAt->format('F j, Y \a\t g:i A'),
            'detail_groups' => $detailGroups,
            'messages' => $messages,
        ];
    }
}
