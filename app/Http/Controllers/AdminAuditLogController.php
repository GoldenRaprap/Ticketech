<?php

namespace App\Http\Controllers;

use App\Models\TicketEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $events = $this->eventsQuery($filters)
            ->paginate(25)
            ->withQueryString();

        return view('staff.audit-logs', [
            'events' => $events,
            'filters' => $filters,
            'actors' => User::query()
                ->whereIn('id', TicketEvent::query()->whereNotNull('actor_id')->select('actor_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name', 'nickname']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $events = $this->eventsQuery($this->validatedFilters($request));

        return response()->streamDownload(function () use ($events): void {
            $path = tempnam(sys_get_temp_dir(), 'ticketech-audit-');

            if ($path === false) {
                throw new RuntimeException('Unable to create the audit log export file.');
            }

            try {
                $writer = new Writer(new Options);
                $writer->openToFile($path);

                try {
                    $sheet = $writer->getCurrentSheet();
                    $sheet->setName('Audit Logs');
                    $sheet->setColumnWidth(22, 1);
                    $sheet->setColumnWidth(18, 2);
                    $sheet->setColumnWidth(18, 3);
                    $sheet->setColumnWidth(30, 4);
                    $sheet->setColumnWidth(18, 5);
                    $sheet->setColumnWidth(18, 6);
                    $sheet->setColumnWidth(28, 7);
                    $sheet->setColumnWidth(18, 8);
                    $sheet->setColumnWidth(32, 9);
                    $sheet->setColumnWidth(72, 10);
                    $sheet->setSheetView((new SheetView)->setShowGridLines(false)->setFreezeRow(2));

                    $headerStyle = (new Style)
                        ->setFontBold()
                        ->setFontColor('1F4E79')
                        ->setBackgroundColor(Color::toARGB('D9EAF7'));

                    $writer->addRow(Row::fromValues([
                        'Date and time',
                        'Actor',
                        'Event',
                        'Target',
                        'Target type',
                        'Request method',
                        'Route',
                        'IP address',
                        'Description',
                        'Audit Details',
                    ], $headerStyle));

                    $lastRow = 1;

                    foreach ($events->with(['actor:id,name', 'ticket:id,ticket_number,subject'])
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->lazy(500) as $event) {
                        $lastRow++;
                        $properties = $event->properties ?? [];
                        $target = $event->ticket
                            ? trim($event->ticket->ticket_number.' · '.$event->ticket->subject)
                            : ($event->subject_name ?? '');
                        $auditDetails = [
                            'User agent: '.$event->user_agent,
                            'Subject ID: '.$event->subject_id,
                        ];

                        if (! empty($properties['before']) || ! empty($properties['after'])) {
                            $auditDetails[] = 'Before: '.json_encode($properties['before'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $auditDetails[] = 'After: '.json_encode($properties['after'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }

                        if (isset($properties['outcome'])) {
                            $auditDetails[] = 'Outcome: '.$properties['outcome'];
                        }

                        if (isset($properties['response_status'])) {
                            $auditDetails[] = 'HTTP status: '.$properties['response_status'];
                        }

                        $writer->addRow(Row::fromValues([
                            $event->created_at?->copy()->timezone(config('app.display_timezone'))->toDateTimeString() ?? '',
                            $this->spreadsheetSafeValue($event->actor?->name ?? $event->actor_name ?? 'Requester'),
                            $this->spreadsheetSafeValue(str($event->event)->headline()->toString()),
                            $this->spreadsheetSafeValue($target),
                            $this->spreadsheetSafeValue($event->subject_type),
                            $this->spreadsheetSafeValue($event->request_method),
                            $this->spreadsheetSafeValue($event->route_name),
                            $this->spreadsheetSafeValue($event->ip_address),
                            $this->spreadsheetSafeValue($event->description),
                            $this->spreadsheetSafeValue(implode("\n", array_filter($auditDetails))),
                        ]));
                    }

                    $sheet->setAutoFilter(new AutoFilter(0, 1, 9, $lastRow));
                } finally {
                    $writer->close();
                }

                if (readfile($path) === false) {
                    throw new RuntimeException('Unable to read the audit log export file.');
                }
            } finally {
                if (is_file($path) && ! unlink($path)) {
                    throw new RuntimeException('Unable to remove the temporary audit log export file.');
                }
            }
        }, 'ticketech-audit-logs-'.now()->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{search?: string, date?: string, actor?: int}
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'actor' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    /**
     * @param  array{search?: string, date?: string, actor?: int}  $filters
     * @return Builder<TicketEvent>
     */
    private function eventsQuery(array $filters): Builder
    {
        return TicketEvent::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('event', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('route_name', 'like', '%'.$search.'%')
                        ->orWhere('subject_name', 'like', '%'.$search.'%')
                        ->orWhere('ip_address', 'like', '%'.$search.'%')
                        ->orWhere('actor_name', 'like', '%'.$search.'%')
                        ->orWhereHas('actor', fn ($actorQuery) => $actorQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('ticket', function ($ticketQuery) use ($search): void {
                            $ticketQuery->where('ticket_number', 'like', '%'.$search.'%')
                                ->orWhere('subject', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['date'] ?? null, function ($query, string $date): void {
                $timezone = config('app.display_timezone');
                $start = CarbonImmutable::parse($date, $timezone)->startOfDay()->utc();
                $end = CarbonImmutable::parse($date, $timezone)->addDay()->startOfDay()->utc();

                $query->where('created_at', '>=', $start)
                    ->where('created_at', '<', $end);
            })
            ->when($filters['actor'] ?? null, fn ($query, int $actorId) => $query->where('actor_id', $actorId))
            ->latest();
    }

    private function spreadsheetSafeValue(?string $value): string
    {
        if ($value !== null && preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1) {
            return "'".$value;
        }

        return $value ?? '';
    }
}
