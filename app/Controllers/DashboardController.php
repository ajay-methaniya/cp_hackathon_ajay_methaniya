<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Agent;
use App\Models\Analysis;
use App\Models\Call;
use App\Services\DashboardCacheService;
use App\Services\KeywordService;
use App\Support\DateRangeFilters;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        if (DateRangeFilters::shouldCanonizeDates($_GET)) {
            $m = DateRangeFilters::mergeDateDefaults($_GET);
            $q = array_filter([
                'date_from' => $m['date_from'],
                'date_to' => $m['date_to'],
                'agent_id' => $m['agent_id'] ?? '',
                'sentiment' => $m['sentiment'] ?? '',
                'min_duration' => $m['min_duration'] ?? '',
                'max_duration' => $m['max_duration'] ?? '',
            ], static fn ($v) => $v !== '' && $v !== null);
            redirect('/dashboard?' . http_build_query($q));
        }
        $filters = $this->filtersFromRequest();
        $bootstrap = [
            'stats' => $this->statsPayloadCached($filters),
            'keywords' => $this->keywordsPayloadCached($filters)['keywords'],
        ];
        $stats = $bootstrap['stats'];
        $timeline = $stats['timeline'] ?? [];
        $sent = $stats['sentiment_distribution'] ?? [];
        $bootBytes = null;
        try {
            $bootBytes = strlen((string) json_encode($bootstrap, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            app_log('error', 'Dashboard bootstrap JSON encode failed for logging', ['message' => $e->getMessage()]);
        }
        app_log('info', 'Dashboard index bootstrap', [
            'filters' => $filters,
            'total_calls' => $stats['total_calls'] ?? null,
            'keywords_count' => count($bootstrap['keywords']),
            'timeline_points' => count(is_array($timeline) ? $timeline : []),
            'sentiment_distribution' => $sent,
            'bootstrap_json_bytes' => $bootBytes,
        ]);
        $this->view('dashboard.index', [
            'title' => 'Dashboard',
            'agents' => Agent::allForDashboard(),
            'filters' => $filters,
            'dashboard_bootstrap' => $bootstrap,
            'export_calls_url' => url('/dashboard/export') . $this->exportQueryString($filters),
            'extra_scripts' => '<script src="' . htmlspecialchars(url('/js/dashboard.js'), ENT_QUOTES) . '"></script>',
        ]);
    }

    /**
     * CSV download for calls matching current dashboard filters (max 1000 rows).
     */
    public function exportCsv(): void
    {
        AuthMiddleware::requireAuth();
        $filters = $this->filtersFromRequest();
        $rows = Call::allForList($filters);
        $rows = array_slice($rows, 0, 1000);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cp-promptx-calls-export.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            return;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'title', 'agent', 'call_date', 'duration_seconds', 'sentiment', 'status', 'created_at']);
        foreach ($rows as $r) {
            $cd = $r['call_date'] ?? '';
            fputcsv($out, [
                (int) ($r['id'] ?? 0),
                (string) ($r['title'] ?? ''),
                (string) ($r['agent_name'] ?? ''),
                $cd !== '' && $cd !== null ? (string) $cd : '',
                isset($r['audio_duration']) && $r['audio_duration'] !== null && $r['audio_duration'] !== '' ? (int) $r['audio_duration'] : '',
                (string) ($r['overall_sentiment'] ?? ''),
                (string) ($r['status'] ?? ''),
                (string) ($r['created_at'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * Query string for export URL (same params as dashboard filters).
     */
    private function exportQueryString(array $filters): string
    {
        $q = array_filter([
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'agent_id' => $filters['agent_id'] ?? '',
            'sentiment' => $filters['sentiment'] ?? '',
            'min_duration' => $filters['min_duration'] ?? '',
            'max_duration' => $filters['max_duration'] ?? '',
        ], static fn ($v) => $v !== '' && $v !== null);

        return $q === [] ? '' : '?' . http_build_query($q);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(): array
    {
        $m = DateRangeFilters::mergeDateDefaults($_GET);

        return [
            'date_from' => (string) $m['date_from'],
            'date_to' => (string) $m['date_to'],
            'agent_id' => isset($m['agent_id']) ? trim((string) $m['agent_id']) : '',
            'sentiment' => isset($m['sentiment']) ? trim((string) $m['sentiment']) : '',
            'min_duration' => isset($m['min_duration']) ? trim((string) $m['min_duration']) : '',
            'max_duration' => isset($m['max_duration']) ? trim((string) $m['max_duration']) : '',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{current:int,previous:int}
     */
    private function callTrend(array $filters): array
    {
        $current = Call::countTotal($filters);
        $prevFilters = $filters;
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            try {
                $from = new \DateTimeImmutable((string) $filters['date_from']);
                $to = new \DateTimeImmutable((string) $filters['date_to']);
                $days = max(1, $from->diff($to)->days + 1);
                $prevTo = $from->modify('-1 day');
                $prevFrom = $prevTo->modify('-' . $days . ' days');
                $prevFilters['date_from'] = $prevFrom->format('Y-m-d');
                $prevFilters['date_to'] = $prevTo->format('Y-m-d');
            } catch (\Throwable) {
                $prevFilters = [];
            }
        } else {
            $prevFilters = [];
        }
        $previous = $prevFilters === [] ? 0 : Call::countTotal($prevFilters);
        return ['current' => $current, 'previous' => $previous];
    }

    /**
     * @return array<string, mixed>
     */
    private function statsPayloadCached(array $filters): array
    {
        $cache = new DashboardCacheService();
        $key = $cache->key('stats', $filters);
        $hit = $cache->get($key);
        if ($hit !== null) {
            return $hit;
        }

        $trend = $this->callTrend($filters);
        $kpis = Analysis::aggregateKpis($filters);
        $sentiment = Analysis::sentimentDistribution($filters);
        $timeline = Call::timelineLastDays(30, $filters);

        $payload = [
            'total_calls' => $trend['current'],
            'total_calls_previous' => $trend['previous'],
            'kpis' => $kpis,
            'sentiment_distribution' => $sentiment,
            'timeline' => $timeline,
        ];
        $cache->set($key, $payload);

        return $payload;
    }

    /**
     * @return array{keywords: list<array<string, mixed>>}
     */
    private function keywordsPayloadCached(array $filters): array
    {
        $cache = new DashboardCacheService();
        $key = $cache->key('keywords', $filters);
        $hit = $cache->get($key);
        if ($hit !== null) {
            return $hit;
        }

        $payload = ['keywords' => (new KeywordService())->topKeywords(10, $filters)];
        $cache->set($key, $payload);

        return $payload;
    }

    public function stats(): void
    {
        AuthMiddleware::requireAuth();
        $this->json($this->statsPayloadCached($this->filtersFromRequest()));
    }

    public function keywords(): void
    {
        AuthMiddleware::requireAuth();
        $this->json($this->keywordsPayloadCached($this->filtersFromRequest()));
    }
}
