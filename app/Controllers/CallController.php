<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Models\Analysis;
use App\Models\Call;
use App\Models\CallNote;
use App\Models\Transcript;
use App\Models\User;
use App\Services\CallPipelineService;
use App\Services\FileStorageService;
use App\Services\SalesPlaybookService;
use App\Services\WhisperService;
use App\Support\DateRangeFilters;

final class CallController extends BaseController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        if (DateRangeFilters::shouldCanonizeDates($_GET)) {
            $m = DateRangeFilters::mergeDateDefaults($_GET);
            $q = array_filter([
                'date_from' => $m['date_from'],
                'date_to' => $m['date_to'],
                'agent_id' => isset($m['agent_id']) ? trim((string) $m['agent_id']) : '',
                'sentiment' => isset($m['sentiment']) ? trim((string) $m['sentiment']) : '',
                'q' => isset($m['q']) ? trim((string) $m['q']) : '',
                'sort' => isset($m['sort']) ? trim((string) $m['sort']) : '',
                'dir' => isset($m['dir']) ? trim((string) $m['dir']) : '',
                'page' => isset($m['page']) ? trim((string) $m['page']) : '',
                'per_page' => isset($m['per_page']) ? trim((string) $m['per_page']) : '',
            ], static fn ($v) => $v !== '' && $v !== null);
            redirect('/calls?' . http_build_query($q));
        }
        $m = DateRangeFilters::mergeDateDefaults($_GET);
        $filters = [
            'date_from' => (string) $m['date_from'],
            'date_to' => (string) $m['date_to'],
            'agent_id' => isset($m['agent_id']) ? trim((string) $m['agent_id']) : '',
            'sentiment' => isset($m['sentiment']) ? trim((string) $m['sentiment']) : '',
            'q' => isset($m['q']) ? trim((string) $m['q']) : '',
        ];
        $sortKeys = ['created_at', 'title', 'agent', 'duration', 'call_date', 'status'];
        $sort = isset($m['sort']) ? trim((string) $m['sort']) : 'created_at';
        if (!in_array($sort, $sortKeys, true)) {
            $sort = 'created_at';
        }
        $dir = strtolower(trim((string) ($m['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($m['page'] ?? 1));
        $perPage = (int) ($m['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 25;
        }

        $listFilters = array_filter($filters, static fn ($v) => $v !== '' && $v !== null);
        $pageData = Call::paginatedForList($listFilters, [
            'q' => $filters['q'],
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $listQueryBase = array_filter([
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'agent_id' => $filters['agent_id'],
            'sentiment' => $filters['sentiment'],
            'q' => $filters['q'],
            'sort' => $sort,
            'dir' => $dir,
            'per_page' => $perPage,
        ], static fn ($v) => $v !== '' && $v !== null);

        $total = $pageData['total'];
        $fromRow = $total === 0 ? 0 : (($pageData['page'] - 1) * $pageData['per_page']) + 1;
        $toRow = min($total, $pageData['page'] * $pageData['per_page']);

        $this->view('calls.index', [
            'title' => 'Calls',
            'calls' => $pageData['rows'],
            'filters' => $filters,
            'agents' => User::agentsForSelect(),
            'sort' => $sort,
            'dir' => $dir,
            'list_query_base' => $listQueryBase,
            'pagination' => [
                'total' => $total,
                'page' => $pageData['page'],
                'per_page' => $pageData['per_page'],
                'total_pages' => $pageData['total_pages'],
                'from' => $fromRow,
                'to' => $toRow,
            ],
            'extra_scripts' => '<script defer src="' . htmlspecialchars(url('/js/calls-list.js'), ENT_QUOTES) . '"></script>',
        ]);
    }

    public function bulkDestroy(): void
    {
        AuthMiddleware::requireAuth();
        if (!verify_csrf()) {
            $this->json(['error' => 'Invalid CSRF'], 403);
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            $this->json(['error' => 'Empty body'], 422);
        }
        try {
            $j = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->json(['error' => 'Invalid JSON'], 422);
        }
        if (!is_array($j)) {
            $this->json(['error' => 'Invalid payload'], 422);
        }
        $ids = $j['ids'] ?? [];
        if (!is_array($ids)) {
            $this->json(['error' => 'ids must be an array'], 422);
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn ($v): int => (int) $v, $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            $this->json(['error' => 'No ids'], 422);
        }
        if (count($ids) > 50) {
            $this->json(['error' => 'Too many ids (max 50)'], 422);
        }
        $storage = new FileStorageService();
        $deleted = 0;
        foreach ($ids as $id) {
            $call = Call::find($id);
            if ($call !== null) {
                $storage->deleteIfExists((string) $call['audio_file_path']);
                Call::delete($id);
                $deleted++;
            }
        }
        $this->json(['ok' => true, 'deleted' => $deleted]);
    }

    public function uploadForm(): void
    {
        AuthMiddleware::requireAuth();
        $this->view('calls.upload', [
            'title' => 'Upload call',
            'agents' => User::agentsForSelect(),
            'current_user_id' => (int) $_SESSION['user_id'],
            'transcription_languages' => config('transcription_languages', []),
            'extra_scripts' => '<script src="' . htmlspecialchars(url('/js/upload.js'), ENT_QUOTES) . '"></script>',
        ]);
    }

    public function store(): void
    {
        AuthMiddleware::requireAuth();
        if (!verify_csrf()) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        RateLimitMiddleware::checkUploads((int) $_SESSION['user_id'], 10);

        // Always prefer JSON for this action (XHR + multipart). Empty $_POST can hide xhr=1 when limits are hit.
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_POST['xhr'] ?? '') === '1'
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        try {
            $storage = new FileStorageService();
            $saved = $storage->storeUploadedAudio($_FILES, 'audio');
        } catch (\Throwable $e) {
            if ($wantsJson) {
                $this->json(['error' => $e->getMessage()], 422);
            }
            $_SESSION['flash_error'] = $e->getMessage();
            redirect('/calls/upload');
        }

        $assignUserId = (int) ($_POST['agent_user_id'] ?? $_SESSION['user_id']);
        $agents = User::agentsForSelect();
        $allowed = array_map(static fn ($a) => (int) $a['id'], $agents);
        if (!in_array($assignUserId, $allowed, true)) {
            $assignUserId = (int) $_SESSION['user_id'];
        }

        $callDate = $_POST['call_date'] ?? null;
        $callDate = is_string($callDate) && $callDate !== '' ? $callDate : null;

        $langPost = trim((string) ($_POST['whisper_language'] ?? ''));
        $whisperHint = WhisperService::normalizeLanguageHint($langPost === '' ? null : $langPost);

        $callId = Call::create([
            'user_id' => $assignUserId,
            'title' => trim((string) ($_POST['title'] ?? 'Untitled call')),
            'audio_file_path' => $saved['path'],
            'audio_format' => $saved['extension'],
            'file_size_bytes' => $saved['size'],
            'status' => 'uploaded',
            'contact_name' => trim((string) ($_POST['contact_name'] ?? '')) ?: null,
            'contact_role' => trim((string) ($_POST['contact_role'] ?? '')) ?: null,
            'contact_tenure' => trim((string) ($_POST['contact_tenure'] ?? '')) ?: null,
            'call_date' => $callDate,
            'whisper_language_hint' => $whisperHint,
        ]);

        $callIdCopy = $callId;
        register_shutdown_function(static function () use ($callIdCopy): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                (new CallPipelineService())->process($callIdCopy);
            } catch (\Throwable $e) {
                app_log('error', 'Pipeline shutdown failed', ['message' => $e->getMessage()]);
            }
        });

        if ($wantsJson) {
            $this->json(['call_id' => $callId, 'status' => 'uploaded']);
        }
        redirect('/calls/' . $callId);
    }

    public function show(int $id): void
    {
        AuthMiddleware::requireAuth();
        $call = Call::find($id);
        if ($call === null) {
            http_response_code(404);
            \App\Support\View::render('errors.404', ['title' => 'Not found']);
            return;
        }
        $transcript = Transcript::forCall($id);
        if ($transcript !== null && isset($transcript['segments']) && is_string($transcript['segments'])) {
            $decoded = json_decode($transcript['segments'], true);
            $transcript['segments'] = is_array($decoded) ? $decoded : [];
        }
        $analysisRow = Analysis::forCall($id);
        $analysis = $this->decodeAnalysis($analysisRow);
        $notes = CallNote::forCall($id);

        $this->view('calls.show', [
            'title' => (string) $call['title'],
            'header_title' => 'Call detail',
            'call' => $call,
            'transcript' => $transcript,
            'analysis' => $analysis,
            'analysisRow' => $analysisRow,
            'notes' => $notes,
            'extra_scripts' => '<script>window.__CALL_PAGE = ' . json_encode([
                'id' => $id,
                'status' => $call['status'],
                'audio_duration_seconds' => isset($call['audio_duration']) ? (int) $call['audio_duration'] : null,
            ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script><script src="' . htmlspecialchars(url('/js/call.js'), ENT_QUOTES) . '"></script>',
        ]);
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private function decodeAnalysis(?array $row): array
    {
        if ($row === null) {
            return [];
        }
        $jsonFields = [
            'sentiment_evolution', 'key_topics', 'keywords_discussed',
            'follow_up_actions', 'positive_observations', 'negative_observations',
            'sales_question_coverage',
        ];
        foreach ($jsonFields as $f) {
            if (!empty($row[$f]) && is_string($row[$f])) {
                $decoded = json_decode($row[$f], true);
                $row[$f] = is_array($decoded) ? $decoded : [];
            }
        }
        $cov = $row['sales_question_coverage'] ?? [];
        if (is_array($cov) && $cov !== []) {
            $row['sales_coverage_score_pct'] = SalesPlaybookService::scoreCoverage($cov);
        }
        return $row;
    }

    public function streamAudio(int $id): void
    {
        AuthMiddleware::requireAuth();
        $call = Call::find($id);
        if ($call === null) {
            http_response_code(404);
            exit;
        }
        $path = (string) $call['audio_file_path'];
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function status(int $id): void
    {
        AuthMiddleware::requireAuth();
        $call = Call::find($id);
        if ($call === null) {
            $this->json(['error' => 'Not found'], 404);
        }
        $payload = ['status' => (string) $call['status']];
        if (($call['status'] ?? '') === 'failed' && !empty($call['last_error'])) {
            $payload['error'] = (string) $call['last_error'];
        }
        $this->json($payload);
    }

    public function destroy(int $id): void
    {
        AuthMiddleware::requireAuth();
        if (!verify_csrf()) {
            $this->json(['error' => 'Invalid CSRF'], 403);
        }
        $call = Call::find($id);
        if ($call === null) {
            $this->json(['error' => 'Not found'], 404);
        }
        $path = (string) $call['audio_file_path'];
        (new FileStorageService())->deleteIfExists($path);
        Call::delete($id);
        $this->json(['ok' => true]);
    }

    public function addNote(int $id): void
    {
        AuthMiddleware::requireAuth();
        if (!verify_csrf()) {
            $this->json(['error' => 'Invalid CSRF'], 403);
        }
        $call = Call::find($id);
        if ($call === null) {
            $this->json(['error' => 'Not found'], 404);
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            $this->json(['error' => 'Note required'], 422);
        }
        CallNote::create($id, (int) $_SESSION['user_id'], $note);
        $this->json(['ok' => true]);
    }

    public function toggleFollowUp(int $id, int $fid): void
    {
        AuthMiddleware::requireAuth();
        if (!verify_csrf()) {
            $this->json(['error' => 'Invalid CSRF'], 403);
        }
        $row = Analysis::forCall($id);
        if ($row === null) {
            $this->json(['error' => 'No analysis'], 404);
        }
        $raw = $row['follow_up_actions'] ?? '[]';
        $list = json_decode((string) $raw, true);
        if (!is_array($list) || !isset($list[$fid])) {
            $this->json(['error' => 'Invalid follow-up'], 422);
        }
        $list[$fid]['completed'] = empty($list[$fid]['completed']);
        Analysis::updateFollowUpsJson($id, json_encode($list, JSON_THROW_ON_ERROR));
        $this->json(['ok' => true, 'completed' => (bool) $list[$fid]['completed']]);
    }

    public function addFollowUp(int $id): void
    {
        AuthMiddleware::requireAuth();
        if (!verify_csrf()) {
            $this->json(['error' => 'Invalid CSRF'], 403);
        }
        $call = Call::find($id);
        if ($call === null) {
            $this->json(['error' => 'Not found'], 404);
        }
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action === '') {
            $this->json(['error' => 'Action required'], 422);
        }
        $row = Analysis::forCall($id);
        $list = [];
        if ($row !== null && !empty($row['follow_up_actions'])) {
            $decoded = json_decode((string) $row['follow_up_actions'], true);
            $list = is_array($decoded) ? $decoded : [];
        }
        $list[] = [
            'action' => $action,
            'priority' => (string) ($_POST['priority'] ?? 'Medium'),
            'owner' => (string) ($_POST['owner'] ?? 'Agent'),
            'completed' => false,
        ];
        if ($row === null) {
            Analysis::saveForCall($id, [
                'follow_up_actions' => $list,
                'overall_sentiment' => 'neutral',
            ]);
        } else {
            Analysis::updateFollowUpsJson($id, json_encode($list, JSON_THROW_ON_ERROR));
        }
        $this->json(['ok' => true]);
    }
}
