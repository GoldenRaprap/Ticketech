<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        [$periodStart, $periodEnd] = $this->periodRange($filters, $period);
        $baseQuery = $this->ticketsInPeriod($periodStart, $periodEnd);
        $summary = $this->summary(clone $baseQuery);
        $trendBuckets = collect($this->trendBuckets($period, $periodStart, $periodEnd));
        $staffWorkload = $this->staffWorkload(clone $baseQuery);

        return view('staff.reports', [
            ...$summary,
            'period' => $period,
            'referenceDate' => $filters['reference_date'] ?? now()->toDateString(),
            'startDate' => $filters['start_date'] ?? '',
            'endDate' => $filters['end_date'] ?? '',
            'periodName' => ucfirst($period),
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodRangeLabel' => $this->formatPeriodRange($periodStart, $periodEnd),
            'trendBuckets' => $trendBuckets,
            'staffWorkload' => $staffWorkload,
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

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $period = $filters['period'] ?? 'weekly';
        [$periodStart, $periodEnd] = $this->periodRange($filters, $period);
        $periodTickets = $this->ticketsInPeriod($periodStart, $periodEnd);
        $summary = $this->summary(clone $periodTickets);
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
        $tickets = (clone $periodTickets)->with('assignedTo:id,name');
        $filename = sprintf(
            'ticketech-report-%s-%s-to-%s.xlsx',
            $period,
            $periodStart->format('Y-m-d'),
            $periodEnd->format('Y-m-d'),
        );

        return response()->streamDownload(function () use (
            $period,
            $periodStart,
            $periodEnd,
            $summary,
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
                        'STAFF PERFORMANCE  |  '.strtoupper($period).' REPORT  |  '
                            .$periodStart->format('M j, Y').' - '.$periodEnd->format('M j, Y'),
                    ], $subtitleStyle, 24));
                    $writer->addRow(Row::fromValues());

                    $cardColors = [
                        [Color::toARGB('1F6FEB'), $paleBlue],
                        [Color::toARGB('C65911'), $paleOrange],
                        [Color::toARGB('078A8A'), $paleTeal],
                        [$purple, $palePurple],
                    ];
                    $cardLabels = ['TOTAL TICKETS', 'OPEN TICKETS', 'RESOLVED / CLOSED', 'RESOLUTION RATE'];
                    $cardValues = [
                        $summary['totalTicketCount'],
                        $summary['openTicketCount'],
                        $summary['resolvedTicketCount'],
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
                    $ticketSheet->setColumnWidth(22, 8, 9);
                    $ticketSheet->setAutoFilter(new AutoFilter(
                        0,
                        1,
                        8,
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
                    $statusStyles = [
                        'New' => (new Style)->setFontBold()->setFontColor(Color::toARGB('1F4E79'))->setBackgroundColor(Color::toARGB('DDEBF7')),
                        'In Progress' => (new Style)->setFontBold()->setFontColor(Color::toARGB('7F6000'))->setBackgroundColor(Color::toARGB('FFF2CC')),
                        'Resolved' => (new Style)->setFontBold()->setFontColor(Color::toARGB('2E7D32'))->setBackgroundColor(Color::toARGB('E2F0D9')),
                        'Closed' => (new Style)->setFontBold()->setFontColor(Color::toARGB('595959'))->setBackgroundColor(Color::toARGB('E7E6E6')),
                    ];
                    $writer->addRow($this->styledRow([
                        'Ticket number',
                        'Subject',
                        'Requester',
                        'Requester email',
                        'Priority',
                        'Status',
                        'Assigned staff',
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
                            $this->spreadsheetSafeValue($ticket->assignedTo?->name ?? 'Unassigned'),
                            $ticket->created_at?->toDateTimeString() ?? '',
                            $ticket->resolved_at?->toDateTimeString() ?? '',
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
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function periodRange(array $filters, string $period): array
    {
        if ($period === 'custom') {
            return [
                CarbonImmutable::parse($filters['start_date'])->startOfDay(),
                CarbonImmutable::parse($filters['end_date'])->endOfDay(),
            ];
        }

        $referenceDate = CarbonImmutable::parse($filters['reference_date'] ?? now()->toDateString());

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

    private function ticketsInPeriod(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Builder
    {
        return Ticket::query()->whereBetween('tickets.created_at', [$periodStart, $periodEnd]);
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

    /**
     * @return list<array{label: string, received: int, resolved: int, receivedHeight: int, resolvedHeight: int}>
     */
    private function trendBuckets(
        string $period,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): array {
        $bucketRanges = $this->trendBucketRanges($period, $periodStart, $periodEnd);
        $receivedCounts = $this->countsByTrendBucket(
            Ticket::query(),
            'tickets.created_at',
            $bucketRanges,
        );
        $resolvedCounts = $this->countsByTrendBucket(
            Ticket::query()->whereIn('status', ['Resolved', 'Closed'])->whereNotNull('resolved_at'),
            'tickets.resolved_at',
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
            $bindings[] = $bucket['start'];
            $bindings[] = $bucket['end'];
        }

        $bucketExpression = 'CASE '.implode(' ', $caseConditions).' END';
        $rangeStart = $bucketRanges[0]['start'];
        $rangeEnd = $bucketRanges[array_key_last($bucketRanges)]['end'];

        return $tickets
            ->whereBetween($dateColumn, [$rangeStart, $rangeEnd])
            ->selectRaw("{$bucketExpression} as report_bucket, COUNT(*) as total", $bindings)
            ->groupBy('report_bucket')
            ->pluck('total', 'report_bucket');
    }

    /**
     * @return Collection<int, object>
     */
    private function staffWorkload(Builder $tickets): Collection
    {
        $rows = $tickets
            ->leftJoin('users as assigned_staff', 'tickets.assigned_to_user_id', '=', 'assigned_staff.id')
            ->selectRaw("COALESCE(assigned_staff.name, 'Unassigned') as staff_name")
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw("SUM(CASE WHEN tickets.status NOT IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw("SUM(CASE WHEN tickets.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed_tickets")
            ->groupBy('tickets.assigned_to_user_id', 'assigned_staff.name')
            ->orderByDesc('total_tickets')
            ->limit(6)
            ->get();
        $maximumCount = max(1, (int) $rows->max('total_tickets'));

        return $rows->map(function (object $row) use ($maximumCount): object {
            $row->workloadWidth = (int) round((int) $row->total_tickets / $maximumCount * 100);

            return $row;
        });
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
            ['Category', 'Tickets', 'Share', 'Volume'],
            (new Style)
                ->setFontBold()
                ->setFontColor(Color::toARGB('40566D'))
                ->setBackgroundColor(Color::toARGB('E8EFF6')),
        ));

        $maximumCount = max(1, (int) $counts->max());

        foreach ($counts as $category => $count) {
            $count = (int) $count;
            $barLength = $count === 0 ? 0 : max(1, (int) round($count / $maximumCount * 20));
            $share = $totalTicketCount === 0 ? 0 : $count / $totalTicketCount;

            $writer->addRow(Row::fromValuesWithStyles([
                $this->spreadsheetSafeValue((string) $category),
                $count,
                $share,
                str_repeat('■', $barLength),
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
}
