<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The window a finance report covers. Both ends are whole days in the app timezone,
 * so "today" and daily buckets follow Nairobi midnight rather than UTC.
 */
final readonly class FinanceDateRange
{
    public const GROUP_BY_DAY = 'day';

    public const GROUP_BY_WEEK = 'week';

    public const GROUP_BY_MONTH = 'month';

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $groupBy = self::GROUP_BY_DAY,
        public bool $excludeTest = true,
    ) {}

    /**
     * @param  array{from?: ?string, to?: ?string, group_by?: ?string, exclude_test?: ?bool}  $input
     */
    public static function fromArray(array $input): self
    {
        $timezone = config('app.timezone');
        $today = CarbonImmutable::today($timezone);

        return new self(
            from: isset($input['from']) ? CarbonImmutable::parse($input['from'], $timezone)->startOfDay() : $today,
            to: isset($input['to']) ? CarbonImmutable::parse($input['to'], $timezone)->endOfDay() : $today->endOfDay(),
            groupBy: $input['group_by'] ?? self::GROUP_BY_DAY,
            excludeTest: $input['exclude_test'] ?? true,
        );
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    public function includesToday(): bool
    {
        return $this->to->greaterThanOrEqualTo(CarbonImmutable::today(config('app.timezone')));
    }

    /**
     * Seconds a report over this window may be cached: closed windows never change, windows
     * that include today are cached briefly.
     */
    public function cacheSeconds(): ?int
    {
        return $this->includesToday() ? (int) config('finance.reports.cache_ttl') : null;
    }

    /**
     * The bucket a moment falls into: Y-m-d for day, the Monday's Y-m-d for week, Y-m for month.
     */
    public function bucketFor(CarbonInterface $moment): string
    {
        $moment = CarbonImmutable::instance($moment)->setTimezone($this->from->getTimezone());

        return match ($this->groupBy) {
            self::GROUP_BY_WEEK => $moment->startOfWeek()->toDateString(),
            self::GROUP_BY_MONTH => $moment->format('Y-m'),
            default => $moment->toDateString(),
        };
    }

    /**
     * Every bucket in the window, so reports can return zero for days with no activity.
     *
     * @return list<string>
     */
    public function buckets(): array
    {
        $buckets = [];

        for ($day = $this->from->startOfDay(); $day->lessThanOrEqualTo($this->to); $day = $day->addDay()) {
            $buckets[$this->bucketFor($day)] = true;
        }

        return array_keys($buckets);
    }
}
