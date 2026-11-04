<?php

namespace App\Services;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;
use UnexpectedValueException;

class TicketResolutionSla
{
    private const WARNING_PERCENT = 80;

    private const BUSINESS_DAY_MINUTES = 480;

    /**
     * @var array<string, array{
     *     priority: string,
     *     class: string,
     *     description: string,
     *     first_response: array{value: int, unit: string},
     *     resolution: array{value: int, unit: string}
     * }> | null
     */
    private ?array $targets = null;

    public function __construct(private SlaSettings $slaSettings) {}

    public function refresh(Ticket $ticket, ?CarbonInterface $cycleStart = null): void
    {
        $cycleStart ??= $ticket->last_reopened_at ?? $ticket->created_at;

        if ($cycleStart === null) {
            throw new LogicException('A persisted ticket is required to calculate its resolution SLA.');
        }

        $priority = strtolower($ticket->priority);
        $target = $this->targets()[$priority]['resolution'] ?? null;

        if ($target === null) {
            throw new UnexpectedValueException("No resolution target is configured for priority [{$ticket->priority}].");
        }

        $targetMinutes = match ($target['unit']) {
            'business_hour' => (int) $target['value'] * 60,
            'business_day' => (int) $target['value'] * self::BUSINESS_DAY_MINUTES,
            default => throw new UnexpectedValueException("Unsupported resolution target unit [{$target['unit']}]."),
        };
        $pausedMinutes = max(0, (int) $ticket->sla_paused_minutes);
        $start = CarbonImmutable::instance($cycleStart)
            ->setTimezone($this->displayTimezone())
            ->startOfMinute();

        $ticket->resolution_warning_at = $this->addBusinessMinutes(
            $start,
            (int) ceil($targetMinutes * self::WARNING_PERCENT / 100) + $pausedMinutes,
        )->setTimezone('UTC');
        $ticket->resolution_due_at = $this->addBusinessMinutes(
            $start,
            $targetMinutes + $pausedMinutes,
        )->setTimezone('UTC');
    }

    public function refreshOpenTickets(): void
    {
        $this->openTickets()
            ->chunkById(100, function (EloquentCollection $tickets): void {
                foreach ($tickets as $ticket) {
                    $this->refresh($ticket);
                    $ticket->save();
                }
            });
    }

    public function initializeMissingDeadlines(): void
    {
        $this->openTickets()
            ->where(function (Builder $query): void {
                $query->whereNull('resolution_due_at')
                    ->orWhere(function (Builder $pausedQuery): void {
                        $pausedQuery
                            ->whereIn('status', ['Waiting for User', 'On Hold'])
                            ->whereNull('sla_paused_at');
                    });
            })
            ->chunkById(100, function (EloquentCollection $tickets): void {
                foreach ($tickets as $ticket) {
                    if ($ticket->resolution_due_at === null) {
                        $this->refresh($ticket);
                    }

                    if (
                        in_array($ticket->status, ['Waiting for User', 'On Hold'], true)
                        && $ticket->sla_paused_at === null
                    ) {
                        $ticket->sla_paused_at = now();
                    }

                    $ticket->save();
                }
            });
    }

    public function businessMinutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = CarbonImmutable::instance($from)->setTimezone($this->displayTimezone());
        $end = CarbonImmutable::instance($to)->setTimezone($this->displayTimezone());

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $seconds = 0;
        $day = $start->startOfDay();

        while ($day->lessThanOrEqualTo($end)) {
            if ($day->isWeekday()) {
                $businessStart = $day->setTime(9, 0);
                $businessEnd = $day->setTime(17, 0);
                $segmentStart = $start->greaterThan($businessStart) ? $start : $businessStart;
                $segmentEnd = $end->lessThan($businessEnd) ? $end : $businessEnd;

                if ($segmentEnd->greaterThan($segmentStart)) {
                    $seconds += $segmentEnd->getTimestamp() - $segmentStart->getTimestamp();
                }
            }

            $day = $day->addDay();
        }

        return intdiv($seconds, 60);
    }

    private function openTickets(): Builder
    {
        return Ticket::query()->whereNotIn('status', ['Resolved', 'Closed']);
    }

    private function addBusinessMinutes(CarbonImmutable $date, int $minutes): CarbonImmutable
    {
        $remainingMinutes = $minutes;
        $cursor = $date;

        while ($remainingMinutes > 0) {
            $cursor = $this->nextBusinessStart($cursor);
            $businessEnd = $cursor->setTime(17, 0);
            $availableMinutes = intdiv($businessEnd->getTimestamp() - $cursor->getTimestamp(), 60);

            if ($remainingMinutes <= $availableMinutes) {
                return $cursor->addMinutes($remainingMinutes);
            }

            $remainingMinutes -= $availableMinutes;
            $cursor = $businessEnd;
        }

        return $cursor;
    }

    private function nextBusinessStart(CarbonImmutable $date): CarbonImmutable
    {
        $cursor = $date;

        while (true) {
            if ($cursor->isWeekend() || $cursor->hour >= 17) {
                $cursor = $cursor->addDay()->startOfDay();

                continue;
            }

            if ($cursor->hour < 9) {
                return $cursor->setTime(9, 0);
            }

            return $cursor;
        }
    }

    private function displayTimezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone', 'UTC'));
    }

    /**
     * @return array<string, array{
     *     priority: string,
     *     class: string,
     *     description: string,
     *     first_response: array{value: int, unit: string},
     *     resolution: array{value: int, unit: string}
     * }>
     */
    private function targets(): array
    {
        return $this->targets ??= $this->slaSettings->targets();
    }
}
