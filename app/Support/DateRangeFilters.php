<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared date range defaults for dashboard, calls list, and reports:
 * first day of current month → today when both are absent; fill single missing side.
 */
final class DateRangeFilters
{
    /**
     * True when the request has no explicit date_from / date_to keys (needs canonical redirect).
     *
     * @param array<string, mixed> $query Typically $_GET
     */
    public static function shouldCanonizeDates(array $query): bool
    {
        return !isset($query['date_from']) && !isset($query['date_to']);
    }

    /**
     * @param array<string, mixed> $query Typically $_GET
     * @return array{0: string, 1: string} [date_from, date_to] as Y-m-d
     */
    public static function resolveDates(array $query): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');

        $dateFrom = isset($query['date_from']) ? trim((string) $query['date_from']) : '';
        $dateTo = isset($query['date_to']) ? trim((string) $query['date_to']) : '';

        if ($dateFrom === '' && $dateTo === '') {
            return [$monthStart, $today];
        }
        if ($dateFrom === '') {
            $dateFrom = $monthStart;
        }
        if ($dateTo === '') {
            $dateTo = $today;
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * Merge resolved date_from / date_to into the query array.
     *
     * @param array<string, mixed> $query Typically $_GET
     * @return array<string, mixed>
     */
    public static function mergeDateDefaults(array $query): array
    {
        [$from, $to] = self::resolveDates($query);
        $out = $query;
        $out['date_from'] = $from;
        $out['date_to'] = $to;

        return $out;
    }
}
