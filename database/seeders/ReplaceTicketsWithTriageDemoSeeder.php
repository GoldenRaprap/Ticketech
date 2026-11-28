<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketDepartmentClassifier;
use App\Services\TicketPriorityClassifier;
use App\Services\TicketResolutionSla;
use App\Services\TicketWorkloadAssigner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReplaceTicketsWithTriageDemoSeeder extends Seeder
{
    /**
     * @var list<array{
     *     category: string,
     *     subject: string,
     *     description: string,
     *     status: string,
     *     resolution_note: ?string
     * }>
     */
    private const TICKET_EXAMPLES = [
        [
            'category' => 'Computer and Printer Hardware',
            'subject' => 'Shared printer remains offline after a network move',
            'description' => 'Staff workstations show a "printer unavailable" message after the shared printer was moved to another office, and its print-server queue cannot be found.',
            'status' => 'Resolved',
            'resolution_note' => 'Reconnected the printer to the approved network port, re-added the print-server queue, and confirmed a test page printed from two staff workstations.',
        ],
        [
            'category' => 'Computer and Printer Hardware',
            'subject' => 'Shared printer unavailable after workstation restart',
            'description' => 'A printer unavailable message appears on one staff workstation after restart, although other staff can still print.',
            'status' => 'Resolved',
            'resolution_note' => 'Removed the stale printer queue, reconnected the workstation to the approved print server, and confirmed the queue remained available after restart.',
        ],
        [
            'category' => 'Computer and Printer Hardware',
            'subject' => 'Lab workstation cannot detect the shared printer',
            'description' => 'The workstation reports a "printer unavailable" message even though nearby computers can print to the shared printer.',
            'status' => 'In Progress',
            'resolution_note' => null,
        ],
        [
            'category' => 'Cybersecurity and Information Security',
            'subject' => 'Phishing message impersonates the university service desk',
            'description' => 'A requester received a suspicious message asking them to follow a link and disclose university credentials. The message appears to impersonate the service desk.',
            'status' => 'Resolved',
            'resolution_note' => 'Confirmed the message was phishing, reported the sender for blocking, and advised the requester to delete it without opening the link.',
        ],
        [
            'category' => 'Cybersecurity and Information Security',
            'subject' => 'Suspicious phishing link reported before credentials were entered',
            'description' => 'A staff member received a phishing email with a suspicious sign-in link and reported it before entering university credentials.',
            'status' => 'Resolved',
            'resolution_note' => 'Confirmed the link led to a credential-harvesting page, submitted the message for blocking, and advised the staff member to delete it without opening the link.',
        ],
        [
            'category' => 'Cybersecurity and Information Security',
            'subject' => 'Suspicious email link reported by a staff member',
            'description' => 'A staff member received a phishing email with an unfamiliar sign-in link and reported it before entering any information.',
            'status' => 'Assigned',
            'resolution_note' => null,
        ],
        [
            'category' => 'Email and Collaboration Services',
            'subject' => 'Shared calendar events are missing from staff mailbox',
            'description' => 'New events added by colleagues do not appear in the shared calendar because it has delayed synchronization, although individual calendars are available.',
            'status' => 'Resolved',
            'resolution_note' => 'Verified the shared calendar permission, removed the stale calendar subscription, and re-added it. Confirmed new test events synchronized.',
        ],
        [
            'category' => 'Email and Collaboration Services',
            'subject' => 'Shared calendar invitation is missing from staff calendar',
            'description' => 'An invitation added to the shared calendar does not appear on the staff member calendar, while other shared calendar events are visible.',
            'status' => 'Resolved',
            'resolution_note' => 'Verified the staff member had permission to the shared calendar, removed the stale subscription, and re-added it. Confirmed the invitation appeared.',
        ],
        [
            'category' => 'Email and Collaboration Services',
            'subject' => 'Shared calendar events do not appear in staff calendar',
            'description' => 'Recent events added to the shared calendar are missing from the staff member calendar after synchronization.',
            'status' => 'Waiting for User',
            'resolution_note' => null,
        ],
        [
            'category' => 'Network Connectivity',
            'subject' => 'Campus Wi-Fi disconnects repeatedly in the computer laboratory',
            'description' => 'Several laboratory devices experience frequent disconnections from the wireless network, while wired computers in the same room stay connected.',
            'status' => 'Resolved',
            'resolution_note' => 'Confirmed the affected access point was restarting, moved the lab devices to the adjacent access point, and escalated the failing unit for replacement.',
        ],
        [
            'category' => 'Network Connectivity',
            'subject' => 'Campus Wi-Fi connection drops in the faculty office',
            'description' => 'The wireless network disconnects repeatedly in one faculty office, while wired computers on the same floor remain connected.',
            'status' => 'Resolved',
            'resolution_note' => 'Confirmed the access point was restarting, moved affected devices to the adjacent access point, and verified the Wi-Fi connection remained stable.',
        ],
        [
            'category' => 'Network Connectivity',
            'subject' => 'Campus Wi-Fi disconnects during online class in the laboratory',
            'description' => 'The wireless network experiences frequent disconnections during online classes in the computer laboratory, while wired devices stay connected.',
            'status' => 'New',
            'resolution_note' => null,
        ],
        [
            'category' => 'Systems and Application Support',
            'subject' => 'Faculty scheduling information system is unavailable',
            'description' => 'A service disruption has made the faculty scheduling information system unavailable to multiple staff members.',
            'status' => 'Resolved',
            'resolution_note' => 'Confirmed the application service had stopped, restarted it after checking the service logs, and verified that staff could load the scheduling system.',
        ],
        [
            'category' => 'Systems and Application Support',
            'subject' => 'Faculty scheduling information system returns intermittent errors',
            'description' => 'The faculty scheduling information system returns a partial system error when staff load the schedule during peak hours.',
            'status' => 'Resolved',
            'resolution_note' => 'Reviewed the scheduling system service logs, corrected the delayed database connection, and verified staff could load the schedule during a test.',
        ],
        [
            'category' => 'Systems and Application Support',
            'subject' => 'Faculty scheduling information system shows a partial system error',
            'description' => 'The faculty scheduling information system shows a partial system error when several staff members load the schedule.',
            'status' => 'In Progress',
            'resolution_note' => null,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(
        TicketDepartmentClassifier $departmentClassifier,
        TicketPriorityClassifier $priorityClassifier,
        TicketResolutionSla $resolutionSla,
        TicketWorkloadAssigner $workloadAssigner,
    ): void {
        if (! app()->environment('local')) {
            throw new RuntimeException('Triage demo tickets can only be generated in the local environment.');
        }

        $attachmentPaths = TicketAttachment::query()
            ->whereIn('ticket_id', Ticket::query()->select('id'))
            ->get(['disk', 'path'])
            ->map(fn (TicketAttachment $attachment): array => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->all();

        DB::transaction(function () use (
            $departmentClassifier,
            $priorityClassifier,
            $resolutionSla,
            $workloadAssigner,
        ): void {
            $categoryNames = array_values(array_unique(array_column(self::TICKET_EXAMPLES, 'category')));
            $categories = Category::query()
                ->whereIn('name', $categoryNames)
                ->where('is_active', true)
                ->whereNotNull('department_id')
                ->whereHas('department', fn ($query) => $query->where('is_active', true))
                ->with('department:id,name,is_active')
                ->get()
                ->keyBy('name');

            if ($categories->count() !== count($categoryNames)) {
                throw new RuntimeException('Activate all categories used by the triage demo before replacing tickets.');
            }

            $requiredDepartmentIds = collect(self::TICKET_EXAMPLES)
                ->reject(fn (array $example): bool => $example['status'] === 'New')
                ->map(fn (array $example): int => (int) $categories[$example['category']]->department_id)
                ->unique()
                ->values();
            $staffDepartmentIds = User::query()
                ->whereIn('role', ['staff', 'department_admin'])
                ->where('employment_status', 'active')
                ->where('staff_approval_status', 'approved')
                ->whereIn('department_id', $requiredDepartmentIds)
                ->distinct()
                ->pluck('department_id')
                ->map(fn (int|string $id): int => (int) $id);
            $departmentsWithoutStaff = $requiredDepartmentIds->diff($staffDepartmentIds);

            if ($departmentsWithoutStaff->isNotEmpty()) {
                throw new RuntimeException('Each demo department needs at least one active, approved staff member before replacing tickets.');
            }

            $examples = self::TICKET_EXAMPLES;
            $classifiedExamples = [];

            foreach ($examples as $example) {
                $category = $departmentClassifier->resolve($example['subject'], $example['description']);

                if ($category === null || ! isset($categories[$example['category']]) || $category->id !== $categories[$example['category']]->id) {
                    throw new RuntimeException("The deterministic classifier did not match demo ticket [{$example['subject']}] to its expected category.");
                }

                $priority = $priorityClassifier->classifyWithMatch(
                    $example['subject'],
                    $example['description'],
                    trim($category->name.' '.$category->description),
                );

                $classifiedExamples[] = [
                    ...$example,
                    'category' => $category,
                    'priority' => $priority,
                ];
            }

            Ticket::query()->delete();

            foreach ($classifiedExamples as $index => $example) {
                $isResolved = in_array($example['status'], ['Resolved', 'Closed'], true);
                $createdAt = $isResolved
                    ? now()->subDays(45 + $index)
                    : now()->subDays(($index % 5) + 1);
                $resolvedAt = $isResolved
                    ? $createdAt->copy()->addDays(($index % 3) + 1)
                    : null;
                $ticket = Ticket::query()->create([
                    'requester_name' => sprintf('Demo Requester %02d', $index + 1),
                    'student_id' => sprintf('DEMO-2026-%04d', $index + 1),
                    'requester_email' => sprintf('demo.requester.%02d@example.test', $index + 1),
                    'requester_email_verified_at' => $createdAt,
                    'department_id' => $example['category']->department_id,
                    'category_id' => $example['category']->id,
                    'subject' => $example['subject'],
                    'description' => $example['description'],
                    'priority' => $example['priority']['priority'],
                    'status' => 'New',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $ticket->ticket_number = sprintf('ED-%s-%06d', $createdAt->format('Y'), $ticket->getKey());
                $assignee = $example['status'] !== 'New'
                    ? $workloadAssigner->assign($ticket)
                    : null;
                $ticket->status = $example['status'];

                if ($assignee !== null) {
                    $ticket->assigned_at = $createdAt->copy()->addHour();
                    $ticket->first_assigned_at = $ticket->assigned_at;
                }

                $ticket->resolved_at = $resolvedAt;
                $ticket->last_resolved_at = $resolvedAt;

                if (in_array($example['status'], ['Waiting for User', 'On Hold'], true)) {
                    $ticket->sla_paused_at = now();
                }

                $resolutionSla->refresh($ticket);
                $ticket->save();
                $ticket->events()->create([
                    'event' => 'ticket_created',
                    'description' => 'Synthetic ticket generated for local triage and resolution-memory demonstration.',
                ]);
                $ticket->events()->create([
                    'actor_name' => 'Automated triage',
                    'event' => 'automated_triage',
                    'description' => "Automated triage matched this ticket to category {$example['category']->name} and department {$example['category']->department->name}.",
                    'properties' => [
                        'routing_status' => $assignee === null ? 'needs_staff_assignment' : 'assigned',
                        'category_id' => $example['category']->id,
                        'routed_category_id' => $example['category']->id,
                        'routing_method' => 'ticket_text',
                        'department_id' => $example['category']->department_id,
                        'assigned_to_user_id' => $assignee?->id,
                        'priority' => $example['priority']['priority'],
                        'priority_keyword' => $example['priority']['keyword'],
                    ],
                ]);

                if ($example['resolution_note'] !== null && $assignee !== null && $resolvedAt !== null) {
                    $ticket->comments()->create([
                        'author_id' => $assignee->id,
                        'author_name' => $assignee->displayName(),
                        'body' => $example['resolution_note'],
                        'is_internal' => false,
                        'created_at' => $resolvedAt,
                        'updated_at' => $resolvedAt,
                    ]);
                }
            }
        });

        foreach ($attachmentPaths as $attachment) {
            $disk = Storage::disk($attachment['disk']);

            if ($disk->exists($attachment['path']) && ! $disk->delete($attachment['path'])) {
                throw new RuntimeException("Unable to remove the replaced ticket attachment [{$attachment['path']}].");
            }
        }

        $distribution = DB::table('tickets')
            ->join('departments', 'tickets.department_id', '=', 'departments.id')
            ->join('categories', 'tickets.category_id', '=', 'categories.id')
            ->select([
                'departments.name as department',
                'categories.name as category',
                'tickets.priority',
                DB::raw('COUNT(*) as ticket_count'),
            ])
            ->groupBy('departments.name', 'categories.name', 'tickets.priority')
            ->orderBy('departments.name')
            ->orderBy('categories.name')
            ->orderBy('tickets.priority')
            ->get();

        $resolvedDemoCount = count(array_filter(
            self::TICKET_EXAMPLES,
            fn (array $example): bool => in_array($example['status'], ['Resolved', 'Closed'], true),
        ));
        $this->command?->info(sprintf(
            'Replaced existing tickets with %d curated local demo tickets, including %d resolved cases with staff resolution notes.',
            count(self::TICKET_EXAMPLES),
            $resolvedDemoCount,
        ));
        $this->command?->table(
            ['Department', 'Category', 'Priority', 'Tickets'],
            $distribution->map(fn (object $row): array => [
                $row->department,
                $row->category,
                $row->priority,
                $row->ticket_count,
            ])->all(),
        );
    }
}
