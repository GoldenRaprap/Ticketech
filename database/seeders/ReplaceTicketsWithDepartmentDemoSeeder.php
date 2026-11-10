<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketResolutionSla;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ReplaceTicketsWithDepartmentDemoSeeder extends Seeder
{
    private const TICKETS_PER_DEPARTMENT = 250;

    /**
     * @var array<string, string>
     */
    private const DEPARTMENTS = [
        'EdTech' => 'edtech',
        'COI - College of Informatics' => 'coi',
        'Registrar' => 'registrar',
        'ICTC' => 'ictc',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const SUBJECTS = [
        'EdTech' => [
            'Learning management system access',
            'Online class course page is missing',
            'Unable to submit an assignment',
            'Classroom projector is not connecting',
            'Student portal activation request',
            'Student email access issue',
        ],
        'COI - College of Informatics' => [
            'Cannot access the programming lab',
            'College portal login issue',
            'Software installation request',
            'Computer lab workstation issue',
            'Course registration system error',
            'Digital resource access request',
        ],
        'Registrar' => [
            'Certificate of enrollment request',
            'Enrollment record correction',
            'Transcript request status',
            'Update personal information on student record',
            'Class schedule discrepancy',
            'Academic document request',
        ],
        'ICTC' => [
            'Campus Wi-Fi connection problem',
            'Network account access request',
            'Computer equipment repair request',
            'VPN connection issue',
            'Shared printer is not responding',
            'Suspicious login alert',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Demo ticket data can only be generated in the local environment.');
        }

        $departments = Department::query()
            ->whereIn('name', array_keys(self::DEPARTMENTS))
            ->where('is_active', true)
            ->get()
            ->keyBy('name');

        if ($departments->count() !== count(self::DEPARTMENTS)) {
            throw new RuntimeException('Activate the EdTech, COI, Registrar, and ICTC departments before generating demo tickets.');
        }

        $categoryIds = Category::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        $attachmentPaths = TicketAttachment::query()
            ->whereIn('ticket_id', Ticket::query()->select('id'))
            ->get(['disk', 'path'])
            ->map(fn (TicketAttachment $attachment): array => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->all();
        $credentials = [];

        DB::transaction(function () use ($departments, $categoryIds, &$credentials): void {
            $this->createDemoStaff($departments, $credentials);
            Ticket::query()->delete();

            $now = now();
            $from = $now->copy()->subMonths(2);
            $statuses = [
                'New',
                'New',
                'Assigned',
                'Assigned',
                'In Progress',
                'In Progress',
                'In Progress',
                'Waiting for User',
                'Waiting for User',
                'On Hold',
                'Resolved',
                'Resolved',
                'Closed',
            ];
            $priorities = ['Urgent', 'High', 'High', 'Medium', 'Medium', 'Medium', 'Low', 'Low'];
            $resolutionSla = app(TicketResolutionSla::class);

            foreach (array_keys(self::DEPARTMENTS) as $departmentName) {
                $department = $departments->get($departmentName);
                $staff = User::query()
                    ->where('role', 'staff')
                    ->where('employment_status', 'active')
                    ->where('staff_approval_status', 'approved')
                    ->where('department_id', $department->id)
                    ->get();

                if ($staff->isEmpty()) {
                    throw new RuntimeException("No approved staff are available for the {$departmentName} department.");
                }

                for ($index = 0; $index < self::TICKETS_PER_DEPARTMENT; $index++) {
                    $status = fake()->randomElement($statuses);
                    $priority = fake()->randomElement($priorities);
                    $createdAt = Carbon::instance(fake()->dateTimeBetween($from, $now));
                    $assignee = $status !== 'New' && ($status === 'Assigned' || fake()->boolean(85))
                        ? $staff->random()
                        : null;
                    $assignedAt = $assignee
                        ? Carbon::instance(fake()->dateTimeBetween($createdAt, $now))
                        : null;
                    $resolvedAt = in_array($status, ['Resolved', 'Closed'], true)
                        ? Carbon::instance(fake()->dateTimeBetween($assignedAt ?? $createdAt, $now))
                        : null;
                    $updatedAt = $resolvedAt ?? ($assignedAt ?? $createdAt);

                    if ($updatedAt->lessThan($createdAt)) {
                        $updatedAt = $createdAt->copy();
                    }

                    $ticket = Ticket::query()->create([
                        'department_id' => $department->id,
                        'category_id' => $categoryIds === [] ? null : fake()->randomElement($categoryIds),
                        'assigned_to_user_id' => $assignee?->id,
                        'requester_name' => fake()->name(),
                        'student_id' => fake()->numerify('2026######'),
                        'requester_email' => fake()->unique()->userName().'@example.test',
                        'requester_email_verified_at' => $createdAt,
                        'requester_phone' => fake()->phoneNumber(),
                        'subject' => fake()->randomElement(self::SUBJECTS[$departmentName]),
                        'description' => fake()->paragraphs(fake()->numberBetween(1, 3), true),
                        'priority' => $priority,
                        'status' => $status,
                        'assigned_at' => $assignedAt,
                        'first_assigned_at' => $assignedAt,
                        'resolved_at' => $resolvedAt,
                        'last_resolved_at' => $resolvedAt,
                    ]);

                    $ticket->ticket_number = sprintf('ED-%s-%06d', $createdAt->format('Y'), $ticket->getKey());
                    $ticket->created_at = $createdAt;
                    $ticket->updated_at = $updatedAt;

                    if (in_array($status, ['Waiting for User', 'On Hold'], true)) {
                        $ticket->sla_paused_at = $now;
                    }

                    $resolutionSla->refresh($ticket, $createdAt);
                    $ticket->save();
                }
            }
        });

        foreach ($attachmentPaths as $attachment) {
            $disk = Storage::disk($attachment['disk']);

            if ($disk->exists($attachment['path']) && ! $disk->delete($attachment['path'])) {
                throw new RuntimeException("Unable to remove the old ticket attachment [{$attachment['path']}].");
            }
        }

        $this->command?->info('Generated 1,000 demo tickets dated within the last two months across four departments.');
        $this->command?->table(['Department', 'Email', 'Password'], $credentials);
    }

    /**
     * @param  Collection<string, Department>  $departments
     * @param  list<array{Department: string, Email: string, Password: string}>  $credentials
     */
    private function createDemoStaff(Collection $departments, array &$credentials): void
    {
        foreach (self::DEPARTMENTS as $departmentName => $slug) {
            $department = $departments->get($departmentName);

            for ($index = 1; $index <= 3; $index++) {
                $email = sprintf('demo.%s.staff.%02d@example.test', $slug, $index);
                $staff = User::query()->firstOrNew(['email' => $email]);

                if ($staff->exists && (
                    $staff->role !== 'staff'
                    || (int) $staff->department_id !== (int) $department->id
                )) {
                    throw new RuntimeException("The demo staff email [{$email}] is already used by another account.");
                }

                $password = Str::random(32);
                $staff->fill([
                    'name' => sprintf('%s Demo Staff %02d', $departmentName, $index),
                    'nickname' => sprintf('%s Staff %02d', $slug, $index),
                    'email' => $email,
                    'password' => $password,
                    'role' => 'staff',
                    'department_id' => $department->id,
                    'employment_status' => 'active',
                ]);
                $staff->email_verified_at = now();
                $staff->staff_approval_status = 'approved';
                $staff->save();

                $credentials[] = [
                    'Department' => $departmentName,
                    'Email' => $email,
                    'Password' => $password,
                ];
            }
        }
    }
}
