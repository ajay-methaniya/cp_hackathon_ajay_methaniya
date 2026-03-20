<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Models\Call;
use PDO;

/**
 * Aggregated analytics for the Reports hub (filters match dashboard).
 */
final class ReportsService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters): array
    {
        $languages = $this->languageDistribution($filters);
        $heatmap = $this->playbookHeatmap($filters);
        $withSamples = array_values(array_filter(
            $heatmap,
            static fn (array $h): bool => ((int) ($h['samples'] ?? 0)) > 0
        ));
        usort($withSamples, static fn (array $a, array $b): int => ($a['avg_score'] ?? 0) <=> ($b['avg_score'] ?? 0));
        $playbookFocus = array_slice($withSamples, 0, 3);

        $totals = $this->totals($filters);
        $langTotal = 0;
        foreach ($languages as $l) {
            $langTotal += (int) ($l['count'] ?? 0);
        }
        $top = $languages[0] ?? null;
        $totals['language_count'] = count($languages);
        $totals['dominant_language'] = is_array($top) ? (string) ($top['label'] ?? '') : null;
        $totals['dominant_language_pct'] = ($langTotal > 0 && is_array($top))
            ? round(100 * (int) ($top['count'] ?? 0) / $langTotal, 0)
            : null;

        return [
            'agents' => $this->agentLeaderboard($filters),
            'languages' => $languages,
            'playbook_heatmap' => $heatmap,
            'playbook_focus' => $playbookFocus,
            'weekly_sentiment' => $this->weeklySentiment($filters),
            'sentiment_trend' => $this->sentimentCountsByMonth($filters),
            'totals' => $totals,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function agentLeaderboard(array $filters): array
    {
        [$where, $params] = $this->callAnalysisWhere($filters);
        $sql = 'SELECT u.id AS user_id, u.name AS agent_name,
                       COUNT(*) AS call_count,
                       AVG(a.sentiment_score) AS avg_sentiment,
                       AVG(a.agent_confidence_score) AS avg_confidence
                FROM calls c
                JOIN users u ON u.id = c.user_id
                JOIN analyses a ON a.call_id = c.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY u.id, u.name
                ORDER BY avg_confidence DESC, call_count DESC';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['call_count'] = (int) $r['call_count'];
            $r['avg_sentiment'] = isset($r['avg_sentiment']) ? round((float) $r['avg_sentiment'], 3) : null;
            $r['avg_confidence'] = isset($r['avg_confidence']) ? round((float) $r['avg_confidence'], 1) : null;
            $r['avg_playbook_pct'] = $this->avgPlaybookForAgent((int) $r['user_id'], $filters);
        }
        unset($r);

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function avgPlaybookForAgent(int $userId, array $filters): ?float
    {
        [$where, $params] = $this->callAnalysisWhere($filters);
        $where[] = 'c.user_id = ?';
        $params[] = $userId;
        $sql = 'SELECT a.sales_question_coverage FROM analyses a
                JOIN calls c ON c.id = a.call_id
                WHERE ' . implode(' AND ', $where) . '
                AND a.sales_question_coverage IS NOT NULL
                AND JSON_LENGTH(a.sales_question_coverage) > 0';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $scores = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cov = json_decode((string) $row['sales_question_coverage'], true);
            if (is_array($cov) && $cov !== []) {
                $scores[] = SalesPlaybookService::scoreCoverage($cov);
            }
        }
        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 1);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{language:string,count:int,label:string}>
     */
    private function languageDistribution(array $filters): array
    {
        [$where, $params] = $this->callTranscriptWhere($filters);
        $sql = 'SELECT t.language AS language, COUNT(*) AS cnt
                FROM transcripts t
                JOIN calls c ON c.id = t.call_id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY t.language
                ORDER BY cnt DESC';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = (string) ($row['language'] ?? 'und');
            $out[] = [
                'language' => $code,
                'count' => (int) $row['cnt'],
                'label' => $this->languageLabel($code),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{question_id:string,avg_score:float,stage:string}>
     */
    private function playbookHeatmap(array $filters): array
    {
        [$where, $params] = $this->callAnalysisWhere($filters);
        $sql = 'SELECT a.sales_question_coverage FROM analyses a
                JOIN calls c ON c.id = a.call_id
                WHERE ' . implode(' AND ', $where) . '
                AND a.sales_question_coverage IS NOT NULL
                AND JSON_LENGTH(a.sales_question_coverage) > 0';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);

        $lib = SalesPlaybookService::library();
        $ids = array_map(static fn ($q) => (string) ($q['id'] ?? ''), $lib);
        $sums = array_fill_keys($ids, 0.0);
        $counts = array_fill_keys($ids, 0);
        $stageById = [];
        foreach ($lib as $q) {
            $stageById[(string) $q['id']] = (string) $q['stage'];
        }

        $weights = ['covered' => 1.0, 'partial' => 0.5, 'not_addressed' => 0.0, 'unclear' => 0.0];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cov = json_decode((string) $row['sales_question_coverage'], true);
            if (!is_array($cov)) {
                continue;
            }
            foreach ($cov as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $qid = (string) ($item['question_id'] ?? '');
                if (!isset($sums[$qid])) {
                    continue;
                }
                $st = (string) ($item['status'] ?? 'not_addressed');
                $sums[$qid] += $weights[$st] ?? 0.0;
                $counts[$qid]++;
            }
        }

        $out = [];
        foreach ($ids as $qid) {
            $n = $counts[$qid];
            $avg = $n > 0 ? round(($sums[$qid] / $n) * 100, 1) : 0.0;
            $out[] = [
                'question_id' => $qid,
                'stage' => $stageById[$qid] ?? '',
                'avg_score' => $avg,
                'samples' => $n,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{week:string,avg_sentiment:?float,calls:int}>
     */
    private function weeklySentiment(array $filters): array
    {
        [$where, $params] = $this->callAnalysisWhere($filters);
        $day = Call::effectiveCallDayExpr();
        $sql = 'SELECT
                    DATE(DATE_SUB(' . $day . ', INTERVAL WEEKDAY(' . $day . ') DAY)) AS week_start,
                    AVG(a.sentiment_score) AS avg_s,
                    COUNT(*) AS cnt
                FROM calls c
                JOIN analyses a ON a.call_id = c.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY week_start
                ORDER BY week_start ASC';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'week' => (string) ($r['week_start'] ?? ''),
                'avg_sentiment' => isset($r['avg_s']) ? round((float) $r['avg_s'], 3) : null,
                'calls' => (int) ($r['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{month:string,positive:int,neutral:int,negative:int}>
     */
    private function sentimentCountsByMonth(array $filters): array
    {
        [$where, $params] = $this->callAnalysisWhere($filters);
        $day = Call::effectiveCallDayExpr();
        $sql = 'SELECT DATE_FORMAT(' . $day . ', "%Y-%m") AS ym,
                       SUM(a.overall_sentiment = "positive") AS p,
                       SUM(a.overall_sentiment = "neutral") AS n,
                       SUM(a.overall_sentiment = "negative") AS neg
                FROM calls c
                JOIN analyses a ON a.call_id = c.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY ym
                ORDER BY ym ASC';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'month' => (string) ($row['ym'] ?? ''),
                'positive' => (int) ($row['p'] ?? 0),
                'neutral' => (int) ($row['n'] ?? 0),
                'negative' => (int) ($row['neg'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int|float|null>
     */
    private function totals(array $filters): array
    {
        [$where, $params] = $this->callAnalysisWhere($filters);
        $sql = 'SELECT COUNT(*) AS n, AVG(c.audio_duration) AS avg_dur, SUM(c.audio_duration) AS sum_dur
                FROM calls c
                JOIN analyses a ON a.call_id = c.id
                WHERE ' . implode(' AND ', $where);
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'analyzed_calls' => (int) ($row['n'] ?? 0),
            'avg_duration_seconds' => isset($row['avg_dur']) ? round((float) $row['avg_dur'], 0) : null,
            'total_talk_time_hours' => isset($row['sum_dur']) ? round(((float) $row['sum_dur']) / 3600, 2) : null,
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function callAnalysisWhere(array $filters): array
    {
        $day = Call::effectiveCallDayExpr();
        $where = ["c.status = 'complete'"];
        $params = [];
        if (!empty($filters['date_from'])) {
            $where[] = $day . ' >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = $day . ' <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'c.user_id = ?';
            $params[] = (int) $filters['agent_id'];
        }
        if (!empty($filters['sentiment'])) {
            $where[] = 'a.overall_sentiment = ?';
            $params[] = $filters['sentiment'];
        }
        if (isset($filters['min_duration']) && $filters['min_duration'] !== '') {
            $where[] = 'c.audio_duration >= ?';
            $params[] = (int) $filters['min_duration'];
        }
        if (isset($filters['max_duration']) && $filters['max_duration'] !== '') {
            $where[] = 'c.audio_duration <= ?';
            $params[] = (int) $filters['max_duration'];
        }

        return [$where, $params];
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function callTranscriptWhere(array $filters): array
    {
        $day = Call::effectiveCallDayExpr();
        $where = ["c.status = 'complete'", "t.language IS NOT NULL", "t.language <> ''"];
        $params = [];
        if (!empty($filters['date_from'])) {
            $where[] = $day . ' >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = $day . ' <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'c.user_id = ?';
            $params[] = (int) $filters['agent_id'];
        }
        if (!empty($filters['sentiment'])) {
            $where[] = 'EXISTS (SELECT 1 FROM analyses ax WHERE ax.call_id = c.id AND ax.overall_sentiment = ?)';
            $params[] = $filters['sentiment'];
        }
        if (isset($filters['min_duration']) && $filters['min_duration'] !== '') {
            $where[] = 'c.audio_duration >= ?';
            $params[] = (int) $filters['min_duration'];
        }
        if (isset($filters['max_duration']) && $filters['max_duration'] !== '') {
            $where[] = 'c.audio_duration <= ?';
            $params[] = (int) $filters['max_duration'];
        }

        return [$where, $params];
    }

    private function languageLabel(string $code): string
    {
        $list = config('transcription_languages', []);
        if (!is_array($list)) {
            return strtoupper($code);
        }
        foreach ($list as $row) {
            if (is_array($row) && ($row['code'] ?? '') === $code) {
                return (string) ($row['label'] ?? $code);
            }
        }

        return strtoupper($code);
    }
}
