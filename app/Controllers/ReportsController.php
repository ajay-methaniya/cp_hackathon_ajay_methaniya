<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\Agent;
use App\Services\DashboardCacheService;
use App\Services\ReportsService;
use App\Support\DateRangeFilters;

final class ReportsController extends BaseController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $filters = $this->filtersFromRequest();
        // Canonical URL with explicit dates so native date inputs and shared links stay in sync.
        if (DateRangeFilters::shouldCanonizeDates($_GET)) {
            $q = array_filter([
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'agent_id' => $filters['agent_id'],
                'sentiment' => $filters['sentiment'],
                'min_duration' => $filters['min_duration'],
                'max_duration' => $filters['max_duration'],
            ], static fn ($v) => $v !== '' && $v !== null);
            redirect('/reports?' . http_build_query($q));
        }
        $this->view('reports.index', [
            'title' => 'Reports & analytics',
            'agents' => Agent::allForDashboard(),
            'filters' => $filters,
            'extra_scripts' => '<script defer src="' . htmlspecialchars(url('/js/reports.js'), ENT_QUOTES) . '"></script>',
        ]);
    }

    public function overview(): void
    {
        AuthMiddleware::requireAuth();
        $filters = $this->filtersFromRequest();
        $cache = new DashboardCacheService();
        $key = $cache->key('reports_overview_v2', $filters);
        $hit = $cache->get($key);
        if ($hit !== null) {
            $this->json($hit);
        }
        $payload = (new ReportsService())->overview($filters);
        $cache->set($key, $payload);
        $this->json($payload);
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
}
