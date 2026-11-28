<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaRescueAssessor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffReportsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $period = $filters['period'] ?? 'weekly';
        $user = $request->user();
        $selectedDepartmentId = $this->selectedDepartmentId($user, $filters);
        [$periodStart, $periodEnd] = $this->periodRange($filters, $period);
        $baseQuery = $this->ticketsInPeriod($user, $periodStart, $periodEnd, $selectedDepartmentId);
        $summary = $this->summary(clone $baseQuery);
        $slaBreachCount = $this->slaBreachCount(clone $baseQuery);
        [$slaAtRiskTicketCount, $slaRiskFactorCounts] = $this->slaRiskInsights(clone $baseQuery);
        $trendBuckets = collect($this->trendBuckets($user, $period, $periodStart, $periodEnd, $selectedDepartmentId));
        $resolvedInPeriodCount = $trendBuckets->sum('resolved');
        $assigneeWorkload = $this->assigneeWorkload(clone $baseQuery);
        $actionableInsights = $this->actionableInsights(
            clone $baseQuery,
            $summary['totalTicketCount'],
            $resolvedInPeriodCount,
            $slaBreachCount,
            $slaAtRiskTicketCount,
            $assigneeWorkload,
        );
        $reportFilters = $this->reportFilterParameters($filters, $period, $user, $selectedDepartmentId);

        return view('staff.reports', [
            ...$summary,
            'resolvedInPeriodCount' => $resolvedInPeriodCount,
            'actionableInsights' => $actionableInsights,
            'slaBreachCount' => $slaBreachCount,
            'slaAtRiskTicketCount' => $slaAtRiskTicketCount,
            'slaRiskFactorCounts' => $slaRiskFactorCounts,
            'period' => $period,
            'referenceDate' => $filters['reference_date'] ?? now()->toDateString(),
            'startDate' => $filters['start_date'] ?? '',
            'endDate' => $filters['end_date'] ?? '',
            'periodName' => ucfirst($period),
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodRangeLabel' => $this->formatPeriodRange($periodStart, $periodEnd),
            'departmentFilterAvailable' => $user->isAdmin() || $user->isDepartmentAdmin(),
            'departments' => $user->isAdmin() || $user->isDepartmentAdmin()
                ? Department::query()->orderBy('name')->get(['id', 'name', 'is_active'])
                : collect(),
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedDepartmentName' => $this->departmentLabel($user, $selectedDepartmentId),
            'reportFilters' => $reportFilters,
            'trendBuckets' => $trendBuckets,
            'assigneeWorkload' => $assigneeWorkload,
            'assignees' => $user->isAdmin()
                ? User::query()
                    ->whereIn('role', ['staff', 'department_admin', 'admin'])
                    ->where('employment_status', 'active')
                    ->where('staff_approval_status', 'approved')
                    ->when($selectedDepartmentId !== null, fn ($query) => $query->where(function ($query) use ($selectedDepartmentId): void {
                        $query->where('role', 'admin')
                            ->orWhere(function ($query) use ($selectedDepartmentId): void {
                                $query->whereIn('role', ['staff', 'department_admin'])
                                    ->where('department_id', $selectedDepartmentId);
                            });
                    }))
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get(['id', 'name', 'nickname', 'email', 'role'])
                : collect(),
            'priorityCounts' => (clone $baseQuery)
                ->selectRaw('priority, COUNT(*) as total')
                ->groupBy('priority')
                ->orderBy('priority')
                ->pluck('total', 'priority'),
            'statusCounts' => (clone $baseQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function insightTickets(Request $request, string $insight): View
    {
        if (! in_array($insight, [
            'sla_breached',
            'sla_at_risk',
            'received',
            'resolved',
            'assignment_balance',
            'category',
        ], true)) {
            abort(404);
        }

        $filters = $this->validatedFilters($request);
        $insightFilters = $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'assignee_ids' => ['nullable', 'array', 'min:2', 'max:2'],
            'assignee_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
        ]);

        if ($insight === 'category' && ! isset($insightFilters['category_id'])) {
            abort(404);
        }

        if ($insight === 'assignment_balance' && count($insightFilters['assignee_ids'] ?? []) !== 2) {
            abort(404);
        }

        $period = $filters['period'] ?? 'weekly';
        $user = $request->user();
        $selectedDepartmentId = $this->selectedDepartmentId($user, $filters);
        [$periodStart, $periodEnd] = $this->periodRange($filters, $period);
        $periodTickets = $this->ticketsInPeriod($user, $periodStart, $periodEnd, $selectedDepartmentId);
        $query = match ($insight) {
            'sla_breached' => $this->slaBreachedTickets($periodTickets),
            'sla_at_risk' => $this->slaAtRiskTickets($periodTickets),
            'received' => $periodTickets,
            'resolved' => $this->ticketsForDepartment($user, $selectedDepartmentId)
                ->whereRaw(
                    'COALESCE(tickets.last_resolved_at, tickets.resolved_at) BETWEEN ? AND ?',
                    [$periodStart->utc(), $periodEnd->utc()],
                ),
            'assignment_balance' => $periodTickets
                ->whereNotIn('tickets.status', ['Resolved', 'Closed'])
                ->whereIn('tickets.assigned_to_user_id', $insightFilters['assignee_ids']),
            'category' => $periodTickets->where('tickets.category_id', $insightFilters['category_id']),
        };
        $insightTitle = match ($insight) {
            'sla_breached' => 'Tickets that breached their resolution target',
            'sla_at_risk' => 'Tickets currently at risk',
            'received' => 'Tickets received in this period',
            'resolved' => 'Tickets resolved in this period',
            'assignment_balance' => 'Open tickets in the workload comparison',
            'category' => 'Tickets in the recurring category',
        };
        $reportFilters = $this->reportFilterParameters($filters, $period, $user, $selectedDepartmentId);

        return view('staff.report-tickets', [
            'tickets' => $query
                ->with(['department:id,name', 'assignedTo:id,name'])
                ->orderByDesc('tickets.created_at')
                ->paginate(25)
                ->withQueryString(),
            'insightTitle' => $insightTitle,
            'periodRangeLabel' => $this->formatPeriodRange($periodStart, $periodEnd),
            'selectedDepartmentName' => $this->departmentLabel($user, $selectedDepartmentId),
            'reportFilters' => $reportFilters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $period = $filters['period'] ?? 'weekly';
        $user = $request->user();
        $selectedDepartmentId = $this->selectedDepartmentId($user, $filters);
        $departmentLabel = $this->departmentLabel($user, $selectedDepartmentId);
        [$periodStart, $periodEnd] = $this->periodRange($filters, $period);
        $periodTickets = $this->ticketsInPeriod($user, $periodStart, $periodEnd, $selectedDepartmentId);
        $summary = $this->summary(clone $periodTickets);
        $resolvedInPeriodCount = $this->resolvedInPeriodCount(
            $user,
            $periodStart,
            $periodEnd,
            $selectedDepartmentId,
        );
        $priorityCounts = (clone $periodTickets)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->orderBy('priority')
            ->pluck('total', 'priority');
        $statusCounts = (clone $periodTickets)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('total', 'status');
        $tickets = (clone $periodTickets)->with('assignedTo:id,name,nickname,role');
        $filename = sprintf(
            'ticketech-report-%s-%s-%s-to-%s.xlsx',
            $period,
            str($departmentLabel)->slug(),
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
        );

        return response()->streamDownload(function () use (
            $period,
            $departmentLabel,
            $periodStart,
            $periodEnd,
            $summary,
            $resolvedInPeriodCount,
            $priorityCounts,
            $statusCounts,
            $tickets,
        ): void {
            $path = tempnam(sys_get_temp_dir(), 'ticketech-report-');

            if ($path === false) {
                throw new RuntimeException('Unable to create the report export file.');
            }

            try {
                $options = new Options;
                $options->mergeCells(0, 1, 3, 1);
                $options->mergeCells(0, 2, 3, 2);
                $writer = new Writer($options);
                $writer->openToFile($path);

                try {
                    $summarySheet = $writer->getCurrentSheet();
                    $summarySheet->setName('Summary');
                    $summarySheet->setColumnWidth(28, 1);
                    $summarySheet->setColumnWidth(18, 2);
                    $summarySheet->setColumnWidth(18, 3);
                    $summarySheet->setColumnWidth(34, 4);
                    $summarySheet->setSheetView((new SheetView)->setShowGridLines(false));

                    $titleBackground = Color::toARGB('DCEAF7');
                    $sectionBackground = Color::toARGB('E8EFF6');
                    $purple = Color::toARGB('6950A1');
                    $paleBlue = Color::toARGB('EAF2FF');
                    $paleTeal = Color::toARGB('E7F5F3');
                    $palePurple = Color::toARGB('F1EDFA');
                    $paleOrange = Color::toARGB('FFF3E8');
                    $paleGray = Color::toARGB('F3F6F9');
                    $border = new Border(
                        new BorderPart(Border::BOTTOM, Color::toARGB('D8E1EA'), Border::WIDTH_THIN),
                    );
                    $titleStyle = (new Style)
                        ->setFontBold()
                        ->setFontSize(18)
                        ->setFontColor(Color::toARGB('18324B'))
                        ->setBackgroundColor($titleBackground)
                        ->setCellAlignment(CellAlignment::CENTER);
                    $subtitleStyle = (new Style)
                        ->setFontSize(11)
                        ->setFontColor(Color::toARGB('40566D'))
                        ->setBackgroundColor(Color::toARGB('F3F6F9'))
                        ->setCellAlignment(CellAlignment::CENTER);
                    $sectionStyle = (new Style)
                        ->setFontBold()
                        ->setFontColor(Color::toARGB('18324B'))
                        ->setBackgroundColor($sectionBackground);
                    $labelStyle = (new Style)
                        ->setFontBold()
                        ->setFontColor(Color::toARGB('40566D'))
                        ->setBackgroundColor($paleGray)
                        ->setBorder($border);
                    $detailStyle = (new Style)
                        ->setFontColor(Color::toARGB('18324B'))
                        ->setBackgroundColor(Color::toARGB(Color::WHITE))
                        ->setBorder($border);
                    $centerStyle = (new Style)->setCellAlignment(CellAlignment::CENTER);
                    $countStyle = (new Style)
                        ->setFontBold()
                        ->setFontColor(Color::toARGB('18324B'))
                        ->setCellAlignment(CellAlignment::CENTER);
                    $percentStyle = (new Style)
                        ->setCellAlignment(CellAlignment::CENTER)
                        ->setFormat('0.0%');
                    $barStyle = (new Style)
                        ->setFontColor(Color::toARGB('1F6FEB'))
                        ->setFontSize(14);

                    $writer->addRow($this->styledRow(['Ticketech Helpdesk Report'], $titleStyle, 34));
                    $writer->addRow($this->styledRow([
                        'HELPDESK PERFORMANCE  |  '.strtoupper($period).' REPORT  |  '
                            .$periodStart->format('M j, Y').' - '.$periodEnd->format('M j, Y')
                            .'  |  '.strtoupper($departmentLabel),
                    ], $subtitleStyle, 24));
                    $writer->addRow(Row::fromValues());

                    $cardColors = [
                        [Color::toARGB('1F6FEB'), $paleBlue],
                        [Color::toARGB('C65911'), $paleOrange],
                        [Color::toARGB('078A8A'), $paleTeal],
                        [$purple, $palePurple],
                    ];
                    $cardLabels = ['TOTAL TICKETS', 'OPEN TICKETS', 'RESOLVED DURING PERIOD', 'RESOLUTION RATE'];
                    $cardValues = [
                        $summary['totalTicketCount'],
                        $summary['openTicketCount'],
                        $resolvedInPeriodCount,
                        $summary['resolutionRate'].'%',
                    ];
                    $cardLabelStyles = [];
                    $cardValueStyles = [];

                    foreach ($cardColors as [$accent, $background]) {
                        $cardLabelStyles[] = (new Style)
                            ->setFontBold()
                            ->setFontColor($accent)
                            ->setBackgroundColor($background)
                            ->setCellAlignment(CellAlignment::CENTER);
                        $cardValueStyles[] = (new Style)
                            ->setFontBold()
                            ->setFontSize(18)
                            ->setFontColor($accent)
                            ->setBackgroundColor($background)
                            ->setCellAlignment(CellAlignment::CENTER);
                    }

                    $writer->addRow(Row::fromValuesWithStyles($cardLabels, null, $cardLabelStyles));
                    $writer->addRow($this->styledRow(
                        $cardValues,
                        new Style,
                        32,
                        $cardValueStyles,
                    ));
                    $writer->addRow(Row::fromValues());
                    $writer->addRow(Row::fromValues(['REPORTING PERIOD', '', '', ''], $sectionStyle));
                    $writer->addRow(Row::fromValuesWithStyles(
                        ['Period', ucfirst($period), 'Average resolution', $summary['averageResolutionHours'] === null
                            ? 'N/A'
                            : $summary['averageResolutionHours'].' hours'],
                        null,
                        [0 => $labelStyle, 1 => $detailStyle, 2 => $labelStyle, 3 => $detailStyle],
                    ));
                    $writer->addRow(Row::fromValuesWithStyles(
                        ['Start date', $periodStart->toDateString(), 'End date', $periodEnd->toDateString()],
                        null,
                        [0 => $labelStyle, 1 => $detailStyle, 2 => $labelStyle, 3 => $detailStyle],
                    ));
                    $writer->addRow(Row::fromValues());

                    $this->writeBreakdown($writer, 'TICKETS BY PRIORITY', $priorityCounts, $summary['totalTicketCount'], $sectionStyle, $labelStyle, $countStyle, $percentStyle, $barStyle);
                    $writer->addRow(Row::fromValues());
                    $this->writeBreakdown($writer, 'TICKETS BY STATUS', $statusCounts, $summary['totalTicketCount'], $sectionStyle, $labelStyle, $countStyle, $percentStyle, $barStyle);

                    $ticketSheet = $writer->addNewSheetAndMakeItCurrent();
                    $ticketSheet->setName('Tickets');
                    $ticketSheet->setColumnWidth(20, 1);
                    $ticketSheet->setColumnWidth(42, 2);
                    $ticketSheet->setColumnWidth(24, 3);
                    $ticketSheet->setColumnWidth(34, 4);
                    $ticketSheet->setColumnWidth(14, 5);
                    $ticketSheet->setColumnWidth(16, 6);
                    $ticketSheet->setColumnWidth(24, 7);
                    $ticketSheet->setColumnWidth(22, 8, 10);
                    $ticketSheet->setAutoFilter(new AutoFilter(
                        0,
                        1,
                        9,
                        max(2, $summary['totalTicketCount'] + 1),
                    ));
                    $ticketSheet->setSheetView(
                        (new SheetView)->setShowGridLines(false)->setFreezeRow(2),
                    );
                    $tableHeaderStyle = (new Style)
                        ->setFontBold()
                        ->setFontColor(Color::toARGB('18324B'))
                        ->setBackgroundColor($titleBackground)
                        ->setShouldWrapText();
                    $stripeStyle = (new Style)->setBackgroundColor(Color::toARGB('F3F6F9'));
                    $priorityStyles = [
                        'Urgent' => (new Style)->setFontBold()->setFontColor(Color::toARGB('9C1C1C'))->setBackgroundColor(Color::toARGB('FCE4E4')),
                        'High' => (new Style)->setFontBold()->setFontColor(Color::toARGB('8A3B08'))->setBackgroundColor(Color::toARGB('FDEBD7')),
                        'Medium' => (new Style)->setFontBold()->setFontColor(Color::toARGB('7F6000'))->setBackgroundColor(Color::toARGB('FFF2CC')),
                        'Low' => (new Style)->setFontBold()->setFontColor(Color::toARGB('22612A'))->setBackgroundColor(Color::toARGB('E2F0D9')),
                    ];
                    $statusStyles = $this->ticketStatusStyles();
                    $writer->addRow($this->styledRow([
                        'Ticket number',
                        'Subject',
                        'Requester',
                        'Requester email',
                        'Priority',
                        'Status',
                        'Assignee',
                        'Assignee role',
                        'Created at',
                        'Resolved at',
                    ], $tableHeaderStyle, 30));

                    $ticketRow = 0;

                    foreach ($tickets->lazyById(500) as $ticket) {
                        $ticketRow++;
                        $priorityStyle = $priorityStyles[$ticket->priority] ?? $centerStyle;
                        $statusStyle = $statusStyles[$ticket->status] ?? $centerStyle;
                        $writer->addRow(Row::fromValuesWithStyles([
                            $this->spreadsheetSafeValue($ticket->ticket_number),
                            $this->spreadsheetSafeValue($ticket->subject),
                            $this->spreadsheetSafeValue($ticket->requester_name),
                            $this->spreadsheetSafeValue($ticket->requester_email),
                            $this->spreadsheetSafeValue($ticket->priority),
                            $this->spreadsheetSafeValue($ticket->status),
                            $this->spreadsheetSafeValue($ticket->assignedTo?->displayName() ?? 'Unassigned'),
                            $this->spreadsheetSafeValue($this->assigneeRoleLabel($ticket->assignedTo?->role)),
                            $ticket->created_at?->copy()->timezone(config('app.display_timezone'))->toDateTimeString() ?? '',
                            $ticket->resolved_at?->copy()->timezone(config('app.display_timezone'))->toDateTimeString() ?? '',
                        ], $ticketRow % 2 === 0 ? $stripeStyle : null, [
                            4 => $priorityStyle,
                            5 => $statusStyle,
                        ]));
                    }
                } finally {
                    $writer->close();
                }

                if (readfile($path) === false) {
                    throw new RuntimeException('Unable to read the report export file.');
                }
            } finally {
                if (is_file($path) && ! unlink($path)) {
                    throw new RuntimeException('Unable to remove the temporary report export file.');
                }
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportStaffPerformance(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $user = $request->user();
        $selectedDepartmentId = $this->selectedDepartmentId($user, $filters);
        $departmentLabel = $this->departmentLabel($user, $selectedDepartmentId);
        $validatedAssignees = $request->validate([
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query
                        ->whereIn('role', ['staff', 'department_admin', 'admin'])
                        ->where('employment_status', 'active')
                        ->where('staff_approval_status', 'approved')
                        ->when($selectedDepartmentId !== null, fn ($query) => $query->where(function ($query) use ($selectedDepartmentId): void {
                            $query->where('role', 'admin')
                                ->orWhere(function ($query) use ($selectedDepartmentId): void {
                                    $query->whereIn('role', ['staff', 'department_admin'])
                                        ->where('department_id', $selectedDepartmentId);
                                });
                        }))),
            ],
        ]);
        $period = $filters['period'] ?? 'weekly';
        [$periodStart, $periodEnd] = $this->periodRange($filters, $period);
        $assignees = User::query()
            ->whereIn('role', ['staff', 'department_admin', 'admin'])
            ->where('employment_status', 'active')
            ->where('staff_approval_status', 'approved')
            ->when($selectedDepartmentId !== null, fn ($query) => $query->where(function ($query) use ($selectedDepartmentId): void {
                $query->where('role', 'admin')
                    ->orWhere(function ($query) use ($selectedDepartmentId): void {
                        $query->whereIn('role', ['staff', 'department_admin'])
                            ->where('department_id', $selectedDepartmentId);
                    });
            }))
            ->whereKey($validatedAssignees['staff_ids'])
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'nickname', 'email', 'role']);
        $assigneeStats = [];

        foreach ($assignees as $assignee) {
            $assigneeStats[$assignee->id] = [
                'ticket_count' => 0,
                'new_count' => 0,
                'assigned_count' => 0,
                'in_progress_count' => 0,
                'waiting_for_user_count' => 0,
                'on_hold_count' => 0,
                'resolved_count' => 0,
                'closed_count' => 0,
                'resolution_minutes' => 0.0,
                'resolution_count' => 0,
            ];
        }

        $ticketQuery = Ticket::query()
            ->whereBetween('tickets.created_at', [$periodStart->utc(), $periodEnd->utc()])
            ->when($selectedDepartmentId !== null, fn ($query) => $query->where('tickets.department_id', $selectedDepartmentId))
            ->whereIn('assigned_to_user_id', $assignees->modelKeys())
            ->select([
                'id',
                'ticket_number',
                'subject',
                'assigned_to_user_id',
                'priority',
                'status',
                'created_at',
                'resolved_at',
            ]);

        foreach ((clone $ticketQuery)->lazyById(500) as $ticket) {
            $stats = &$assigneeStats[$ticket->assigned_to_user_id];
            $stats['ticket_count']++;

            match ($ticket->status) {
                'New' => $stats['new_count']++,
                'Assigned' => $stats['assigned_count']++,
                'In Progress' => $stats['in_progress_count']++,
                'Waiting for User' => $stats['waiting_for_user_count']++,
                'On Hold' => $stats['on_hold_count']++,
                'Resolved' => $stats['resolved_count']++,
                'Closed' => $stats['closed_count']++,
                default => null,
            };

            if (in_array($ticket->status, ['Resolved', 'Closed'], true) && $ticket->resolved_at !== null) {
                $stats['resolution_minutes'] += $ticket->created_at->diffInMinutes($ticket->resolved_at);
                $stats['resolution_count']++;
            }

            unset($stats);
        }

        $ticketCount = array_sum(array_column($assigneeStats, 'ticket_count'));
        $filename = sprintf(
            'ticketech-assignee-performance-%s-%s-%s-to-%s.xlsx',
            $period,
            str($departmentLabel)->slug(),
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
        );

        return response()->streamDownload(function () use (
            $period,
            $departmentLabel,
            $periodStart,
            $periodEnd,
            $assignees,
            $assigneeStats,
            $ticketCount,
            $ticketQuery,
        ): void {
            $path = tempnam(sys_get_temp_dir(), 'ticketech-assignee-performance-');

            if ($path === false) {
                throw new RuntimeException('Unable to create the assignee performance export file.');
            }

            try {
                $writer = new Writer(new Options);
                $writer->openToFile($path);

                try {
                    $summarySheet = $writer->getCurrentSheet();
                    $summarySheet->setName('Assignee Summary');
                    $summarySheet->setColumnWidth(26, 1);
                    $summarySheet->setColumnWidth(34, 2);
                    $summarySheet->setColumnWidth(17, 3, 11);
                    $summarySheet->setColumnWidth(24, 12);
                    $summarySheet->setSheetView((new SheetView)->setShowGridLines(false)->setFreezeRow(4));
                    $summarySheet->setAutoFilter(new AutoFilter(0, 4, 11, $assignees->count() + 4));
                    $headerStyle = (new Style)
                        ->setFontBold()
                        ->setFontColor(Color::toARGB('18324B'))
                        ->setBackgroundColor(Color::toARGB('DCEAF7'))
                        ->setShouldWrapText();
                    $titleStyle = (new Style)
                        ->setFontBold()
                        ->setFontSize(16)
                        ->setFontColor(Color::toARGB('18324B'));
                    $subtitleStyle = (new Style)->setFontColor(Color::toARGB('40566D'));
                    $percentStyle = (new Style)->setFormat('0.0%');
                    $numberStyle = (new Style)->setFormat('0.0');
                    $ticketStripeStyle = (new Style)->setBackgroundColor(Color::toARGB('F3F6F9'));
                    $ticketStatusStyles = $this->ticketStatusStyles();
                    $unknownStatusStyle = (new Style)->setCellAlignment(CellAlignment::CENTER);

                    $writer->addRow(Row::fromValues(['ASSIGNEE PERFORMANCE SUMMARY'], $titleStyle));
                    $writer->addRow(Row::fromValues([
                        strtoupper($period).' REPORT  |  '.$periodStart->format('M j, Y').' - '.$periodEnd->format('M j, Y')
                            .'  |  '.strtoupper($departmentLabel),
                    ], $subtitleStyle));
                    $writer->addRow(Row::fromValues());
                    $writer->addRow(Row::fromValues([
                        'Assignee',
                        'Email',
                        'Tickets assigned',
                        'New',
                        'Assigned',
                        'In Progress',
                        'Waiting for User',
                        'On Hold',
                        'Resolved / closed',
                        'Resolution rate',
                        'Avg resolution (hours)',
                        'Role',
                    ], $headerStyle));

                    foreach ($assignees as $assignee) {
                        $stats = $assigneeStats[$assignee->id];
                        $completedCount = $stats['resolved_count'] + $stats['closed_count'];
                        $resolutionRate = $stats['ticket_count'] === 0
                            ? 0
                            : $completedCount / $stats['ticket_count'];
                        $averageResolutionHours = $stats['resolution_count'] === 0
                            ? null
                            : round($stats['resolution_minutes'] / $stats['resolution_count'] / 60, 1);

                        $writer->addRow(Row::fromValuesWithStyles([
                            $this->spreadsheetSafeValue($assignee->displayName()),
                            $this->spreadsheetSafeValue($assignee->email),
                            $stats['ticket_count'],
                            $stats['new_count'],
                            $stats['assigned_count'],
                            $stats['in_progress_count'],
                            $stats['waiting_for_user_count'],
                            $stats['on_hold_count'],
                            $completedCount,
                            $resolutionRate,
                            $averageResolutionHours,
                            $this->assigneeRoleLabel($assignee->role),
                        ], null, [9 => $percentStyle, 10 => $numberStyle]));
                    }

                    $ticketSheet = $writer->addNewSheetAndMakeItCurrent();
                    $ticketSheet->setName('Ticket Details');
                    $ticketSheet->setColumnWidth(21, 1);
                    $ticketSheet->setColumnWidth(42, 2);
                    $ticketSheet->setColumnWidth(26, 3);
                    $ticketSheet->setColumnWidth(34, 4);
                    $ticketSheet->setColumnWidth(15, 5, 6);
                    $ticketSheet->setColumnWidth(23, 7, 8);
                    $ticketSheet->setColumnWidth(24, 9);
                    $ticketSheet->setColumnWidth(22, 10);
                    $ticketSheet->setSheetView((new SheetView)->setShowGridLines(false)->setFreezeRow(1));
                    $ticketSheet->setAutoFilter(new AutoFilter(0, 1, 9, max(1, $ticketCount + 1)));
                    $writer->addRow(Row::fromValues([
                        'Ticket number',
                        'Subject',
                        'Assignee',
                        'Assignee email',
                        'Priority',
                        'Status',
                        'Created at',
                        'Resolved at',
                        'Resolution time (hours)',
                        'Assignee role',
                    ], $headerStyle));

                    $ticketRow = 0;

                    foreach ((clone $ticketQuery)->with('assignedTo:id,name,nickname,email,role')->lazyById(500) as $ticket) {
                        $ticketRow++;
                        $durationHours = in_array($ticket->status, ['Resolved', 'Closed'], true)
                            && $ticket->resolved_at !== null
                            ? round($ticket->created_at->diffInMinutes($ticket->resolved_at) / 60, 1)
                            : null;

                        $writer->addRow(Row::fromValuesWithStyles([
                            $this->spreadsheetSafeValue($ticket->ticket_number),
                            $this->spreadsheetSafeValue($ticket->subject),
                            $this->spreadsheetSafeValue($ticket->assignedTo?->displayName()),
                            $this->spreadsheetSafeValue($ticket->assignedTo?->email),
                            $this->spreadsheetSafeValue($ticket->priority),
                            $this->spreadsheetSafeValue($ticket->status),
                            $ticket->created_at?->copy()->timezone(config('app.display_timezone'))->toDateTimeString() ?? '',
                            $ticket->resolved_at?->copy()->timezone(config('app.display_timezone'))->toDateTimeString() ?? '',
                            $durationHours,
                            $this->assigneeRoleLabel($ticket->assignedTo?->role),
                        ], $ticketRow % 2 === 0 ? $ticketStripeStyle : null, [
                            5 => $ticketStatusStyles[$ticket->status] ?? $unknownStatusStyle,
                        ]));
                    }
                } finally {
                    $writer->close();
                }

                if (readfile($path) === false) {
                    throw new RuntimeException('Unable to read the staff performance export file.');
                }
            } finally {
                if (is_file($path) && ! unlink($path)) {
                    throw new RuntimeException('Unable to remove the temporary staff performance export file.');
                }
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly,yearly,custom'],
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'department_id' => $request->user()->isAdmin() || $request->user()->isDepartmentAdmin()
                ? ['nullable', 'integer', Rule::exists('departments', 'id')]
                : ['prohibited'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string>
     */
    private function reportFilterParameters(
        array $filters,
        string $period,
        User $user,
        ?int $departmentId,
    ): array {
        $parameters = ['period' => $period];

        if ($period === 'custom') {
            $parameters['start_date'] = $filters['start_date'];
            $parameters['end_date'] = $filters['end_date'];
        } else {
            $parameters['reference_date'] = $filters['reference_date']
                ?? now(config('app.display_timezone'))->toDateString();
        }

        if (
            $departmentId !== null
            && ($user->isAdmin() || $user->isDepartmentAdmin())
        ) {
            $parameters['department_id'] = $departmentId;
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function selectedDepartmentId(User $user, array $filters): ?int
    {
        if ($user->isAdmin() || $user->isDepartmentAdmin()) {
            return filled($filters['department_id'] ?? null) ? (int) $filters['department_id'] : null;
        }

        return $user->department_id;
    }

    private function departmentLabel(User $user, ?int $departmentId): string
    {
        if ($departmentId === null) {
            return $user->isAdmin() || $user->isDepartmentAdmin() ? 'All departments' : 'Unassigned';
        }

        return Department::query()->whereKey($departmentId)->value('name') ?? 'Unassigned';
    }

    private function ticketsForDepartment(User $user, ?int $departmentId): Builder
    {
        $tickets = Ticket::query();

        if ($user->isAdmin() || $user->isDepartmentAdmin()) {
            return $tickets->when(
                $departmentId !== null,
                fn (Builder $query) => $query->where('tickets.department_id', $departmentId),
            );
        }

        return $tickets->visibleTo($user);
    }

    private function resolvedInPeriodCount(
        User $user,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?int $departmentId,
    ): int {
        return $this->ticketsForDepartment($user, $departmentId)
            ->whereRaw(
                'COALESCE(tickets.last_resolved_at, tickets.resolved_at) BETWEEN ? AND ?',
                [$periodStart->utc(), $periodEnd->utc()],
            )
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function periodRange(array $filters, string $period): array
    {
        if ($period === 'custom') {
            $timezone = config('app.display_timezone');

            return [
                CarbonImmutable::parse($filters['start_date'], $timezone)->startOfDay(),
                CarbonImmutable::parse($filters['end_date'], $timezone)->endOfDay(),
            ];
        }

        $referenceDate = CarbonImmutable::parse(
            $filters['reference_date'] ?? now(config('app.display_timezone'))->toDateString(),
            config('app.display_timezone'),
        );

        return match ($period) {
            'daily' => [$referenceDate->startOfDay(), $referenceDate->endOfDay()],
            'monthly' => [$referenceDate->startOfMonth(), $referenceDate->endOfMonth()],
            'yearly' => [$referenceDate->startOfYear(), $referenceDate->endOfYear()],
            default => [
                $referenceDate->startOfWeek(CarbonImmutable::MONDAY),
                $referenceDate->endOfWeek(CarbonImmutable::SUNDAY),
            ],
        };
    }

    private function ticketsInPeriod(
        User $user,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?int $departmentId,
    ): Builder {
        return $this->ticketsForDepartment($user, $departmentId)
            ->whereBetween('tickets.created_at', [$periodStart->utc(), $periodEnd->utc()]);
    }

    /**
     * @return array{totalTicketCount: int, openTicketCount: int, resolvedTicketCount: int, resolutionRate: float, averageResolutionHours: float|null}
     */
    private function summary(Builder $tickets): array
    {
        $totalTicketCount = (clone $tickets)->count();
        $resolvedTicketCount = (clone $tickets)->whereIn('status', ['Resolved', 'Closed'])->count();
        $resolvedTickets = (clone $tickets)
            ->whereIn('status', ['Resolved', 'Closed'])
            ->whereNotNull('resolved_at')
            ->select(['id', 'created_at', 'resolved_at']);
        $resolutionMinutes = 0.0;
        $resolvedTicketCountWithDuration = 0;

        foreach ($resolvedTickets->lazyById(500) as $ticket) {
            $resolutionMinutes += $ticket->created_at->diffInMinutes($ticket->resolved_at);
            $resolvedTicketCountWithDuration++;
        }

        $averageResolutionHours = $resolvedTicketCountWithDuration === 0
            ? null
            : round($resolutionMinutes / $resolvedTicketCountWithDuration / 60, 1);

        return [
            'totalTicketCount' => $totalTicketCount,
            'openTicketCount' => (clone $tickets)->whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'resolvedTicketCount' => $resolvedTicketCount,
            'resolutionRate' => $totalTicketCount === 0 ? 0 : round($resolvedTicketCount / $totalTicketCount * 100, 1),
            'averageResolutionHours' => $averageResolutionHours,
        ];
    }

    private function slaBreachCount(Builder $tickets): int
    {
        return $this->slaBreachedTickets($tickets)->count();
    }

    private function slaBreachedTickets(Builder $tickets): Builder
    {
        return $tickets
            ->whereNotNull('resolution_due_at')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $openTickets): void {
                    $openTickets
                        ->whereIn('status', ['New', 'Assigned', 'In Progress'])
                        ->where('resolution_due_at', '<=', now());
                })->orWhere(function (Builder $pausedTickets): void {
                    $pausedTickets
                        ->whereIn('status', ['Waiting for User', 'On Hold'])
                        ->whereNotNull('sla_paused_at')
                        ->whereColumn('resolution_due_at', '<=', 'sla_paused_at');
                })->orWhere(function (Builder $completedTickets): void {
                    $completedTickets
                        ->whereIn('status', ['Resolved', 'Closed'])
                        ->whereNotNull('resolved_at')
                        ->whereColumn('resolved_at', '>', 'resolution_due_at');
                });
            });
    }

    private function slaAtRiskTickets(Builder $tickets): Builder
    {
        return $tickets
            ->whereIn('status', ['New', 'Assigned', 'In Progress'])
            ->where('sla_risk_score', '>=', SlaRescueAssessor::MINIMUM_RISK_SCORE);
    }

    /**
     * @return array{int, Collection<string, int>}
     */
    private function slaRiskInsights(Builder $tickets): array
    {
        $atRiskTickets = $this->slaAtRiskTickets($tickets)
            ->get(['sla_risk_factors']);
        $factorsByTicket = $atRiskTickets
            ->map(fn (Ticket $ticket): array => $ticket->sla_risk_factors ?? [])
            ->values();
        $factorCounts = collect(SlaRescueAssessor::factorLabels())
            ->mapWithKeys(fn (string $label, string $factor): array => [
                $label => $factorsByTicket
                    ->filter(fn (array $factors): bool => in_array($factor, $factors, true))
                    ->count(),
            ])
            ->filter(fn (int $count): bool => $count > 0);

        return [$atRiskTickets->count(), $factorCounts];
    }

    /**
     * @param  Collection<int, object>  $assigneeWorkload
     * @return list<array{
     *     type: string,
     *     title: string,
     *     message: string,
     *     evidence: string,
     *     action: string,
     *     ticket_links: list<array{insight: string, label: string, category_id?: int, assignee_ids?: list<int>}>
     * }>
     */
    private function actionableInsights(
        Builder $periodTickets,
        int $incomingCount,
        int $resolvedInPeriodCount,
        int $slaBreachCount,
        int $slaAtRiskTicketCount,
        Collection $assigneeWorkload,
    ): array {
        $insights = [];

        if ($slaBreachCount > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Resolution targets were missed',
                'message' => "{$slaBreachCount} tickets from the selected creation period have exceeded their resolution target.",
                'evidence' => 'Based on recorded resolution deadlines and current ticket status.',
                'action' => 'Review overdue tickets and identify any blocked handoffs.',
                'ticket_links' => [
                    ['insight' => 'sla_breached', 'label' => 'Show matching tickets'],
                ],
            ];
        } elseif ($slaAtRiskTicketCount > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Tickets may miss their resolution targets',
                'message' => "{$slaAtRiskTicketCount} tickets from the selected creation period are currently marked at risk.",
                'evidence' => 'Risk scores are based on the ticket queue and SLA indicators.',
                'action' => 'Prioritize at-risk work and review whether assignments need support.',
                'ticket_links' => [
                    ['insight' => 'sla_at_risk', 'label' => 'Show matching tickets'],
                ],
            ];
        }

        if ($incomingCount > 0 || $resolvedInPeriodCount > 0) {
            $isIntakeHigher = $incomingCount > $resolvedInPeriodCount;
            $difference = abs($incomingCount - $resolvedInPeriodCount);
            $activityTicketLinks = [];

            if ($incomingCount > 0) {
                $activityTicketLinks[] = ['insight' => 'received', 'label' => 'View received tickets'];
            }

            if ($resolvedInPeriodCount > 0) {
                $activityTicketLinks[] = ['insight' => 'resolved', 'label' => 'View resolved tickets'];
            }

            $insights[] = [
                'type' => $isIntakeHigher ? 'info' : 'success',
                'title' => $isIntakeHigher
                    ? 'New intake exceeded resolutions'
                    : ($difference === 0 ? 'Intake and resolutions were even' : 'Resolution activity exceeded new intake'),
                'message' => $isIntakeHigher
                    ? "The team received {$incomingCount} tickets and recorded {$resolvedInPeriodCount} resolutions in this period."
                    : "The team recorded {$resolvedInPeriodCount} resolutions against {$incomingCount} tickets received in this period.",
                'evidence' => $difference === 0
                    ? 'Incoming and resolved counts are even for this period.'
                    : 'These are period activity counts; a resolution may belong to a ticket created earlier.',
                'action' => $isIntakeHigher
                    ? 'Check the open-ticket count and redistribute work if the backlog is growing.'
                    : 'Continue monitoring incoming volume and SLA risk.',
                'ticket_links' => $activityTicketLinks,
            ];
        }

        $openAssignments = $assigneeWorkload
            ->filter(fn (object $assignee): bool => $assignee->assignee_role !== null)
            ->map(fn (object $assignee): array => [
                'id' => (int) $assignee->assignee_id,
                'name' => (string) $assignee->assignee_name,
                'count' => (int) $assignee->open_tickets,
            ])
            ->filter(fn (array $assignee): bool => $assignee['count'] > 0)
            ->sortBy('count')
            ->values();

        if ($openAssignments->count() >= 2) {
            $leastLoaded = $openAssignments->first();
            $mostLoaded = $openAssignments->last();

            if ($mostLoaded['count'] >= 2 * $leastLoaded['count']) {
                $insights[] = [
                    'type' => 'info',
                    'title' => 'Open assignments are unevenly distributed',
                    'message' => "{$mostLoaded['name']} has {$mostLoaded['count']} open tickets in this report cohort, compared with {$leastLoaded['name']}’s {$leastLoaded['count']}.",
                    'evidence' => 'Compares open assigned tickets created during the selected period.',
                    'action' => 'Review assignments and consider rebalancing work where skills and availability allow.',
                    'ticket_links' => [
                        [
                            'insight' => 'assignment_balance',
                            'label' => 'Show compared tickets',
                            'assignee_ids' => [$leastLoaded['id'], $mostLoaded['id']],
                        ],
                    ],
                ];
            }
        }

        $recurringCategories = (clone $periodTickets)
            ->join('categories', 'categories.id', '=', 'tickets.category_id')
            ->selectRaw('categories.id as category_id')
            ->selectRaw('categories.name as category_name, COUNT(tickets.id) as ticket_count')
            ->groupBy('categories.id', 'categories.name')
            ->havingRaw('COUNT(tickets.id) >= ?', [3])
            ->orderByDesc('ticket_count')
            ->limit(3)
            ->get();

        if ($recurringCategories->isNotEmpty()) {
            $publishedArticleCategories = KnowledgeArticle::query()
                ->where('is_published', true)
                ->pluck('category')
                ->map(fn (string $category): string => mb_strtolower(trim($category)))
                ->all();

            foreach ($recurringCategories as $recurringCategory) {
                $categoryName = (string) $recurringCategory->category_name;
                $hasPublishedArticle = in_array(
                    mb_strtolower(trim($categoryName)),
                    $publishedArticleCategories,
                    true,
                );
                $ticketCount = (int) $recurringCategory->ticket_count;
                $insights[] = [
                    'type' => $hasPublishedArticle ? 'info' : 'opportunity',
                    'title' => $hasPublishedArticle
                        ? "Review the {$categoryName} article"
                        : "Recurring issue: {$categoryName}",
                    'message' => "{$ticketCount} tickets in this period share the {$categoryName} category.",
                    'evidence' => $hasPublishedArticle
                        ? 'A published article is tagged with this category.'
                        : 'No published article currently uses this category label.',
                    'action' => $hasPublishedArticle
                        ? 'Check whether the article addresses the concerns raised in these tickets.'
                        : 'Consider creating a staff-reviewed article for this recurring issue.',
                    'ticket_links' => [
                        [
                            'insight' => 'category',
                            'label' => 'Show matching tickets',
                            'category_id' => (int) $recurringCategory->category_id,
                        ],
                    ],
                ];
            }
        }

        return $insights;
    }

    /**
     * @return list<array{label: string, received: int, resolved: int, receivedHeight: int, resolvedHeight: int}>
     */
    private function trendBuckets(
        User $user,
        string $period,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?int $departmentId,
    ): array {
        $bucketRanges = $this->trendBucketRanges($period, $periodStart, $periodEnd);
        $receivedCounts = $this->countsByTrendBucket(
            $this->ticketsForDepartment($user, $departmentId),
            'tickets.created_at',
            $bucketRanges,
        );
        $resolvedCounts = $this->countsByTrendBucket(
            $this->ticketsForDepartment($user, $departmentId),
            'COALESCE(tickets.last_resolved_at, tickets.resolved_at)',
            $bucketRanges,
        );
        $maximumCount = 1;

        foreach ($bucketRanges as $index => &$bucket) {
            $bucket['received'] = (int) $receivedCounts->get($index, 0);
            $bucket['resolved'] = (int) $resolvedCounts->get($index, 0);
            $maximumCount = max($maximumCount, $bucket['received'], $bucket['resolved']);
        }

        unset($bucket);

        foreach ($bucketRanges as &$bucket) {
            $bucket['receivedHeight'] = (int) round($bucket['received'] / $maximumCount * 100);
            $bucket['resolvedHeight'] = (int) round($bucket['resolved'] / $maximumCount * 100);
        }

        unset($bucket);

        return $bucketRanges;
    }

    /**
     * @return list<array{label: string, start: CarbonImmutable, end: CarbonImmutable, received?: int, resolved?: int, receivedHeight?: int, resolvedHeight?: int}>
     */
    private function trendBucketRanges(
        string $period,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): array {
        $rangeDays = (int) $periodStart->startOfDay()->diffInDays($periodEnd->startOfDay()) + 1;
        $buckets = [];

        if ($period === 'weekly' || $period === 'daily' || $period === 'monthly' || ($period === 'custom' && $rangeDays <= 31)) {
            for ($bucketDate = $periodStart->startOfDay(); $bucketDate <= $periodEnd; $bucketDate = $bucketDate->addDay()) {
                $bucketEnd = $bucketDate->endOfDay();

                if ($bucketEnd > $periodEnd) {
                    $bucketEnd = $periodEnd;
                }

                $buckets[] = [
                    'label' => $bucketDate->format('M j'),
                    'start' => $bucketDate,
                    'end' => $bucketEnd,
                ];
            }

            return $buckets;
        }

        if ($period === 'yearly' || ($period === 'custom' && $rangeDays <= 366)) {
            $includeYear = $periodStart->year !== $periodEnd->year;

            for ($bucketMonth = $periodStart->startOfMonth(); $bucketMonth <= $periodEnd; $bucketMonth = $bucketMonth->addMonth()) {
                $bucketStart = $bucketMonth < $periodStart ? $periodStart : $bucketMonth;
                $bucketEnd = $bucketMonth->endOfMonth();

                if ($bucketEnd > $periodEnd) {
                    $bucketEnd = $periodEnd;
                }

                $buckets[] = [
                    'label' => $bucketMonth->format($includeYear ? 'M Y' : 'M'),
                    'start' => $bucketStart,
                    'end' => $bucketEnd,
                ];
            }

            return $buckets;
        }

        $bucketCount = min(12, $rangeDays);
        $daysPerBucket = (int) ceil($rangeDays / $bucketCount);

        for ($bucketIndex = 0; $bucketIndex < $bucketCount; $bucketIndex++) {
            $bucketStart = $periodStart->startOfDay()->addDays($bucketIndex * $daysPerBucket);
            $bucketEnd = $bucketStart->addDays($daysPerBucket - 1)->endOfDay();

            if ($bucketEnd > $periodEnd) {
                $bucketEnd = $periodEnd;
            }

            $label = $bucketStart->year === $bucketEnd->year
                ? $bucketStart->format('M j')
                : $bucketStart->format('Y').'–'.$bucketEnd->format('Y');

            $buckets[] = [
                'label' => $label,
                'start' => $bucketStart,
                'end' => $bucketEnd,
            ];
        }

        return $buckets;
    }

    /**
     * @param  list<array{label: string, start: CarbonImmutable, end: CarbonImmutable}>  $bucketRanges
     * @return Collection<int, int>
     */
    private function countsByTrendBucket(Builder $tickets, string $dateColumn, array $bucketRanges): Collection
    {
        $caseConditions = [];
        $bindings = [];

        foreach ($bucketRanges as $index => $bucket) {
            $caseConditions[] = "WHEN {$dateColumn} BETWEEN ? AND ? THEN {$index}";
            $bindings[] = $bucket['start']->utc();
            $bindings[] = $bucket['end']->utc();
        }

        $bucketExpression = 'CASE '.implode(' ', $caseConditions).' END';
        $rangeStart = $bucketRanges[0]['start']->utc();
        $rangeEnd = $bucketRanges[array_key_last($bucketRanges)]['end']->utc();

        return $tickets
            ->whereRaw("{$dateColumn} BETWEEN ? AND ?", [$rangeStart, $rangeEnd])
            ->selectRaw("{$bucketExpression} as report_bucket, COUNT(*) as total", $bindings)
            ->groupBy('report_bucket')
            ->pluck('total', 'report_bucket');
    }

    /**
     * @return Collection<int, object>
     */
    private function assigneeWorkload(Builder $tickets): Collection
    {
        $rows = $tickets
            ->leftJoin('users as assigned_staff', 'tickets.assigned_to_user_id', '=', 'assigned_staff.id')
            ->selectRaw('tickets.assigned_to_user_id as assignee_id')
            ->selectRaw("COALESCE(assigned_staff.nickname, assigned_staff.name, 'Unassigned') as assignee_name")
            ->selectRaw('assigned_staff.role as assignee_role')
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw("SUM(CASE WHEN tickets.status NOT IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw("SUM(CASE WHEN tickets.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed_tickets")
            ->groupBy('tickets.assigned_to_user_id', 'assigned_staff.nickname', 'assigned_staff.name', 'assigned_staff.role')
            ->orderByDesc('total_tickets')
            ->limit(6)
            ->get();
        $maximumCount = max(1, (int) $rows->max('total_tickets'));

        return $rows->map(function (object $row) use ($maximumCount): object {
            $row->workloadWidth = (int) round((int) $row->total_tickets / $maximumCount * 100);
            $row->assignee_role_label = $this->assigneeRoleLabel($row->assignee_role);

            return $row;
        });
    }

    private function assigneeRoleLabel(?string $role): string
    {
        return match ($role) {
            null => '',
            'admin' => 'Super Admin',
            'department_admin' => 'Department Admin',
            'staff' => 'Staff',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }

    private function formatPeriodRange(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): string
    {
        if ($periodStart->isSameDay($periodEnd)) {
            return $periodStart->format('M j, Y');
        }

        return $periodStart->format('M j, Y').' – '.$periodEnd->format('M j, Y');
    }

    private function writeBreakdown(
        Writer $writer,
        string $title,
        Collection $counts,
        int $totalTicketCount,
        Style $sectionStyle,
        Style $categoryStyle,
        Style $countStyle,
        Style $percentStyle,
        Style $barStyle,
    ): void {
        $writer->addRow(Row::fromValues([$title, '', '', ''], $sectionStyle));
        $writer->addRow(Row::fromValues(
            ['Category', 'Tickets', 'Share', 'Volume (each block = 5%)'],
            (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('40566D'))
                ->setBackgroundColor(Color::toARGB('E8EFF6')),
        ));

        foreach ($counts as $category => $count) {
            $count = (int) $count;
            $share = $totalTicketCount === 0 ? 0 : $count / $totalTicketCount;
            $barLength = $count === 0 || $totalTicketCount === 0
                ? 0
                : max(1, (int) round($share * 20));
            $bar = str_repeat('■', $barLength).str_repeat('□', 20 - $barLength);

            $writer->addRow(Row::fromValuesWithStyles([
                $this->spreadsheetSafeValue((string) $category),
                $count,
                $share,
                $bar,
            ], null, [
                0 => $categoryStyle,
                1 => $countStyle,
                2 => $percentStyle,
                3 => $barStyle,
            ]));
        }
    }

    /**
     * @param  list<null|bool|float|int|string>  $values
     * @param  array<int, Style>  $columnStyles
     */
    private function styledRow(array $values, Style $rowStyle, float $height, array $columnStyles = []): Row
    {
        return Row::fromValuesWithStyles($values, $rowStyle, $columnStyles)->setHeight($height);
    }

    private function spreadsheetSafeValue(?string $value): string
    {
        if ($value !== null && preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1) {
            return "'".$value;
        }

        return $value ?? '';
    }

    /**
     * @return array<string, Style>
     */
    private function ticketStatusStyles(): array
    {
        return [
            'New' => (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('1F4E79'))
                ->setBackgroundColor(Color::toARGB('DDEBF7'))
                ->setCellAlignment(CellAlignment::CENTER),
            'Assigned' => (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('0F6B78'))
                ->setBackgroundColor(Color::toARGB('DDEFF2'))
                ->setCellAlignment(CellAlignment::CENTER),
            'In Progress' => (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('7F6000'))
                ->setBackgroundColor(Color::toARGB('FFF2CC'))
                ->setCellAlignment(CellAlignment::CENTER),
            'Waiting for User' => (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('9C5700'))
                ->setBackgroundColor(Color::toARGB('FCE4D6'))
                ->setCellAlignment(CellAlignment::CENTER),
            'On Hold' => (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('44546A'))
                ->setBackgroundColor(Color::toARGB('E2E8F0'))
                ->setCellAlignment(CellAlignment::CENTER),
            'Resolved' => (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('2E7D32'))
                ->setBackgroundColor(Color::toARGB('E2F0D9'))
                ->setCellAlignment(CellAlignment::CENTER),
            'Closed' => (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('595959'))
                ->setBackgroundColor(Color::toARGB('E7E6E6'))
                ->setCellAlignment(CellAlignment::CENTER),
        ];
    }
}
