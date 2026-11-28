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

    /**
     * @var array{opening_time: string, closing_time: string, minutes_per_day: int} | null
     */
    private ?array $businessHours = null;

    public function __construct(private SlaSettings $slaSettings) {}

    public function refresh(Ticket $ticket, ?CarbonInterface $cycleStart = null): void
    {
        $cycleStart ??= $ticket->created_at;

        if ($cycleStart === null) {
            throw new LogicException('A persisted ticket is required to calculate its resolution SLA.');
        }

        $targetMinutes = $this->targetBusinessMinutes($ticket->priority, 'resolution');
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
        $this->businessHours = $this->slaSettings->businessHours();

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
        $businessHours = $this->businessHours();
        $start = CarbonImmutable::instance($from)->setTimezone($this->displayTimezone());
        $end = CarbonImmutable::instance($to)->setTimezone($this->displayTimezone());

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $seconds = 0;
        $day = $start->startOfDay();

        while ($day->lessThanOrEqualTo($end)) {
            if ($day->isWeekday()) {
                $businessStart = $this->atBusinessTime($day, $businessHours['opening_time']);
                $businessEnd = $this->atBusinessTime($day, $businessHours['closing_time']);
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

    /**
     * @param  'first_response'|'resolution'  $targetName
     */
    public function targetBusinessMinutes(string $priority, string $targetName): int
    {
        $target = $this->targets()[strtolower($priority)][$targetName] ?? null;

        if ($target === null) {
            throw new UnexpectedValueException("No {$targetName} target is configured for priority [{$priority}].");
        }

        return $this->minutesForTarget($target, $targetName);
    }

    private function openTickets(): Builder
    {
        return Ticket::query()->whereNotIn('status', ['Resolved', 'Closed']);
    }

    /**
     * @param  array{value: int, unit: string}  $target
     */
    private function minutesForTarget(array $target, string $targetName): int
    {
        return match ($target['unit']) {
            'business_hour' => (int) $target['value'] * 60,
            'business_day' => (int) $target['value'] * $this->businessHours()['minutes_per_day'],
            default => throw new UnexpectedValueException("Unsupported {$targetName} target unit [{$target['unit']}]."),
        };
    }

    private function addBusinessMinutes(CarbonImmutable $date, int $minutes): CarbonImmutable
    {
        $businessHours = $this->businessHours();
        $remainingMinutes = $minutes;
        $cursor = $date;

        while ($remainingMinutes > 0) {
            $cursor = $this->nextBusinessStart($cursor, $businessHours);
            $businessEnd = $this->atBusinessTime($cursor, $businessHours['closing_time']);
            $availableMinutes = intdiv($businessEnd->getTimestamp() - $cursor->getTimestamp(), 60);

            if ($remainingMinutes <= $availableMinutes) {
                return $cursor->addMinutes($remainingMinutes);
            }

            $remainingMinutes -= $availableMinutes;
            $cursor = $businessEnd;
        }

        return $cursor;
    }

    /**
     * @param  array{opening_time: string, closing_time: string, minutes_per_day: int}  $businessHours
     */
    private function nextBusinessStart(CarbonImmutable $date, array $businessHours): CarbonImmutable
    {
        $cursor = $date;

        while (true) {
            $businessStart = $this->atBusinessTime($cursor, $businessHours['opening_time']);
            $businessEnd = $this->atBusinessTime($cursor, $businessHours['closing_time']);

            if ($cursor->isWeekend() || $cursor->greaterThanOrEqualTo($businessEnd)) {
                $cursor = $cursor->addDay()->startOfDay();

                continue;
            }

            if ($cursor->lessThan($businessStart)) {
                return $businessStart;
            }

            return $cursor;
        }
    }

    private function atBusinessTime(
        CarbonImmutable $date,
        string $time,
    ): CarbonImmutable {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $date->setTime($hours, $minutes);
    }

    /**
     * @return array{opening_time: string, closing_time: string, minutes_per_day: int}
     */
    private function businessHours(): array
    {
        return $this->businessHours ??= $this->slaSettings->businessHours();
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
