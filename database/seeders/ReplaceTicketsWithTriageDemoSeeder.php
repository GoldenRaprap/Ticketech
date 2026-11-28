<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
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
    private const TICKET_COUNT = 50;

    /**
     * @var array<string, array<string, array{description: string, keywords: string}>>
     */
    private const CATEGORY_DEFINITIONS = [
        'Multimedia' => [
            'Classroom AV Equipment' => [
                'description' => 'Projectors, classroom displays, microphones, and speakers.',
                'keywords' => 'projector, classroom display, HDMI, microphone, speakers, screen',
            ],
            'Media Recording and Editing' => [
                'description' => 'Camera, audio and video recording, editing, and media export.',
                'keywords' => 'camera, video recording, audio recording, video editing, audio editing, media export, rendering',
            ],
            'Creative Software Licensing' => [
                'description' => 'Creative Cloud access, design applications, and software licenses.',
                'keywords' => 'creative cloud, Adobe, Photoshop, Premiere Pro, software license, design software',
            ],
        ],
        'ICTC' => [
            'Network Connectivity' => [
                'description' => 'Wireless network, internet, VPN, and wired connectivity.',
                'keywords' => 'wireless network, Wi-Fi, internet connection, VPN, ethernet, router',
            ],
            'Account Login and Access' => [
                'description' => 'User accounts, password resets, sign-in, and authentication.',
                'keywords' => 'user account, password reset, sign in, authentication, account locked, login access',
            ],
            'Computer and Printer Hardware' => [
                'description' => 'Computer, laptop, printer, keyboard, and workstation hardware.',
                'keywords' => 'computer, laptop, printer, keyboard, mouse, workstation, hardware repair',
            ],
        ],
        'Technical' => [
            'Learning Platform Issues' => [
                'description' => 'Learning platform, course pages, assignments, and class materials.',
                'keywords' => 'learning platform, course page, assignment upload, LMS, class materials',
            ],
            'Student Portal and Records' => [
                'description' => 'Student portal, enrollment, registration, and academic records.',
                'keywords' => 'student portal, enrollment, registration, transcript, academic records, grade record',
            ],
            'Application Installation and Errors' => [
                'description' => 'Application installation, software updates, and application errors.',
                'keywords' => 'application installation, software update, application error, program crash, install software',
            ],
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const PRIORITY_CONTEXT = [
        'Urgent' => 'Priority context: this is an urgent incident requiring immediate attention.',
        'High' => 'Priority context: this is a high priority issue blocking the requester.',
        'Medium' => 'Priority context: this is a medium priority issue with a workaround available.',
        'Low' => 'Priority context: this is a routine request with no interruption to normal work.',
    ];

    /**
     * @var list<array{category: string, subject: string, description: string}>
     */
    private const TICKET_TEMPLATES = [
        ['category' => 'Classroom AV Equipment', 'subject' => 'Classroom projector displays a blank image', 'description' => 'The classroom projector powers on, but no image appears on the screen.'],
        ['category' => 'Classroom AV Equipment', 'subject' => 'HDMI input missing on classroom display', 'description' => 'The classroom display does not detect the connected HDMI source.'],
        ['category' => 'Media Recording and Editing', 'subject' => 'Camera recording cannot be transferred', 'description' => 'A recorded video cannot be imported from the camera to the editing workstation.'],
        ['category' => 'Media Recording and Editing', 'subject' => 'Video editing export stops before completion', 'description' => 'The media export stops while rendering a project in the video editor.'],
        ['category' => 'Creative Software Licensing', 'subject' => 'Adobe Photoshop license is unavailable', 'description' => 'The design software reports that the creative license is not assigned.'],
        ['category' => 'Creative Software Licensing', 'subject' => 'Premiere Pro asks for a software license', 'description' => 'The video editing application requests a license when it starts.'],
        ['category' => 'Network Connectivity', 'subject' => 'Wireless network disconnects in the computer lab', 'description' => 'The Wi-Fi connection drops repeatedly while using the lab computers.'],
        ['category' => 'Network Connectivity', 'subject' => 'VPN cannot connect to the campus network', 'description' => 'The VPN client does not establish an internet connection to campus resources.'],
        ['category' => 'Account Login and Access', 'subject' => 'User account locked after password reset', 'description' => 'The account remains locked after the requester completed a password reset.'],
        ['category' => 'Account Login and Access', 'subject' => 'Sign in authentication rejects valid credentials', 'description' => 'The login page rejects the requester during authentication.'],
        ['category' => 'Computer and Printer Hardware', 'subject' => 'Printer unavailable from the staff workstation', 'description' => 'The workstation cannot find the shared printer in the hardware list.'],
        ['category' => 'Computer and Printer Hardware', 'subject' => 'Laptop keyboard keys stop responding', 'description' => 'Several keys on the laptop keyboard do not respond during normal use.'],
        ['category' => 'Learning Platform Issues', 'subject' => 'Learning platform course page is missing', 'description' => 'A course page and its class materials are not visible in the learning platform.'],
        ['category' => 'Learning Platform Issues', 'subject' => 'Assignment upload fails in the LMS', 'description' => 'The LMS does not complete the assignment upload for the course.'],
        ['category' => 'Student Portal and Records', 'subject' => 'Student portal enrollment record is incorrect', 'description' => 'The enrollment details shown in the student portal do not match the current schedule.'],
        ['category' => 'Student Portal and Records', 'subject' => 'Transcript request is missing from academic records', 'description' => 'The requester cannot find the transcript request in the academic records page.'],
        ['category' => 'Application Installation and Errors', 'subject' => 'Application installation stops on the final step', 'description' => 'The software installation process stops before the application can be opened.'],
        ['category' => 'Application Installation and Errors', 'subject' => 'Application error appears after software update', 'description' => 'The application displays an error after the latest software update.'],
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
            $departments = Department::query()
                ->whereIn('name', array_keys(self::CATEGORY_DEFINITIONS))
                ->where('is_active', true)
                ->get()
                ->keyBy('name');

            if ($departments->count() !== count(self::CATEGORY_DEFINITIONS)) {
                throw new RuntimeException('Activate the Multimedia, ICTC, and Technical departments before generating triage demo tickets.');
            }

            $categories = [];

            foreach (self::CATEGORY_DEFINITIONS as $departmentName => $departmentCategories) {
                foreach ($departmentCategories as $name => $details) {
                    $category = Category::query()->firstOrNew(['name' => $name]);

                    if ($category->exists && (int) $category->department_id !== (int) $departments->get($departmentName)->id) {
                        throw new RuntimeException("The category [{$name}] is already assigned to another department.");
                    }

                    $category->fill([
                        'description' => $details['description'],
                        'classification_keywords' => $details['keywords'],
                        'department_id' => $departments->get($departmentName)->id,
                        'is_active' => true,
                    ]);
                    $category->save();
                    $categories[$name] = $category;
                }
            }

            $examples = $this->ticketExamples();
            $classifiedExamples = [];

            foreach ($examples as $example) {
                $category = $departmentClassifier->resolve($example['subject'], $example['description']);

                if ($category === null || ! isset($categories[$example['expected_category']]) || $category->id !== $categories[$example['expected_category']]->id) {
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
                $createdAt = now()->subDays(($index % 30) + 1);
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
                $assignee = $workloadAssigner->assign($ticket);
                $resolutionSla->refresh($ticket);
                $ticket->save();
                $ticket->events()->create([
                    'event' => 'ticket_created',
                    'description' => 'Synthetic triage demo ticket generated locally.',
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

        $this->command?->info('Replaced the existing tickets with exactly 50 locally generated triage demo tickets.');
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

    /**
     * @return list<array{
     *     expected_category: string,
     *     subject: string,
     *     description: string
     * }>
     */
    private function ticketExamples(): array
    {
        $examples = [];
        $priorities = array_keys(self::PRIORITY_CONTEXT);
        $templateCount = count(self::TICKET_TEMPLATES);

        for ($index = 1; $index <= self::TICKET_COUNT; $index++) {
            $template = self::TICKET_TEMPLATES[($index - 1) % $templateCount];
            $priority = $priorities[($index - 1) % count($priorities)];

            $examples[] = [
                'expected_category' => $template['category'],
                'subject' => sprintf('Demo %02d: %s', $index, $template['subject']),
                'description' => $template['description']."\n\n".self::PRIORITY_CONTEXT[$priority],
            ];
        }

        return $examples;
    }
}
