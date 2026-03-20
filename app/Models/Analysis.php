<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use App\Services\SalesPlaybookService;
use PDO;

final class Analysis
{
    /**
     * Insert or replace full analysis row for a call.
     *
     * @param array<string, mixed> $data
     */
    public static function saveForCall(int $callId, array $data): void
    {
        $encode = static function (mixed $v): mixed {
            if (is_array($v)) {
                return json_encode($v, JSON_THROW_ON_ERROR);
            }
            return $v;
        };

        $row = [
            'call_id' => $callId,
            'overall_sentiment' => $data['overall_sentiment'] ?? 'neutral',
            'sentiment_score' => $data['sentiment_score'] ?? null,
            'agent_confidence_score' => $data['agent_confidence_score'] ?? null,
            'agent_liveness_pct' => $data['agent_liveness_pct'] ?? null,
            'previous_handling_score' => $data['previous_handling_score'] ?? null,
            'sentiment_evolution' => $encode($data['sentiment_evolution'] ?? []),
            'call_summary' => $data['call_summary'] ?? null,
            'key_topics' => $encode($data['key_topics'] ?? []),
            'budget_discussed' => !empty($data['budget_discussed']) ? 1 : 0,
            'related_project' => !empty($data['related_project']) ? 1 : 0,
            'business_strategy' => !empty($data['business_strategy']) ? 1 : 0,
            'marketing_discussed' => !empty($data['marketing_discussed']) ? 1 : 0,
            'keywords_discussed' => $encode($data['keywords_discussed'] ?? []),
            'follow_up_actions' => $encode($data['follow_up_actions'] ?? []),
            'positive_observations' => $encode($data['positive_observations'] ?? []),
            'negative_observations' => $encode($data['negative_observations'] ?? []),
            'sales_question_coverage' => $encode($data['sales_question_coverage'] ?? []),
            'gpt_model_used' => $data['gpt_model_used'] ?? 'gpt-4o-mini',
            'tokens_used' => $data['tokens_used'] ?? null,
            'analysis_duration_ms' => $data['analysis_duration_ms'] ?? null,
        ];

        $pdo = Connection::pdo();
        $exists = $pdo->prepare('SELECT id FROM analyses WHERE call_id = ? LIMIT 1');
        $exists->execute([$callId]);
        if ($exists->fetchColumn()) {
            $sql = 'UPDATE analyses SET
                overall_sentiment = ?, sentiment_score = ?, agent_confidence_score = ?, agent_liveness_pct = ?,
                previous_handling_score = ?, sentiment_evolution = ?, call_summary = ?, key_topics = ?,
                budget_discussed = ?, related_project = ?, business_strategy = ?, marketing_discussed = ?,
                keywords_discussed = ?, follow_up_actions = ?, positive_observations = ?, negative_observations = ?,
                sales_question_coverage = ?, gpt_model_used = ?, tokens_used = ?, analysis_duration_ms = ?
                WHERE call_id = ?';
            $pdo->prepare($sql)->execute([
                $row['overall_sentiment'],
                $row['sentiment_score'],
                $row['agent_confidence_score'],
                $row['agent_liveness_pct'],
                $row['previous_handling_score'],
                $row['sentiment_evolution'],
                $row['call_summary'],
                $row['key_topics'],
                $row['budget_discussed'],
                $row['related_project'],
                $row['business_strategy'],
                $row['marketing_discussed'],
                $row['keywords_discussed'],
                $row['follow_up_actions'],
                $row['positive_observations'],
                $row['negative_observations'],
                $row['sales_question_coverage'],
                $row['gpt_model_used'],
                $row['tokens_used'],
                $row['analysis_duration_ms'],
                $callId,
            ]);
            return;
        }

        $sql = 'INSERT INTO analyses (
            call_id, overall_sentiment, sentiment_score, agent_confidence_score, agent_liveness_pct,
            previous_handling_score, sentiment_evolution, call_summary, key_topics,
            budget_discussed, related_project, business_strategy, marketing_discussed,
            keywords_discussed, follow_up_actions, positive_observations, negative_observations,
            sales_question_coverage, gpt_model_used, tokens_used, analysis_duration_ms
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $pdo->prepare($sql)->execute([
            $row['call_id'],
            $row['overall_sentiment'],
            $row['sentiment_score'],
            $row['agent_confidence_score'],
            $row['agent_liveness_pct'],
            $row['previous_handling_score'],
            $row['sentiment_evolution'],
            $row['call_summary'],
            $row['key_topics'],
            $row['budget_discussed'],
            $row['related_project'],
            $row['business_strategy'],
            $row['marketing_discussed'],
            $row['keywords_discussed'],
            $row['follow_up_actions'],
            $row['positive_observations'],
            $row['negative_observations'],
            $row['sales_question_coverage'],
            $row['gpt_model_used'],
            $row['tokens_used'],
            $row['analysis_duration_ms'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forCall(int $callId): ?array
    {
        $stmt = Connection::pdo()->prepare('SELECT * FROM analyses WHERE call_id = ? LIMIT 1');
        $stmt->execute([$callId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string, int>
     */
    public static function sentimentDistribution(array $filters = []): array
    {
        $join = 'JOIN calls c ON c.id = a.call_id';
        $where = ["c.status = 'complete'"];
        $params = [];
        $day = Call::effectiveCallDayExpr();
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
        if (isset($filters['min_duration']) && $filters['min_duration'] !== '') {
            $where[] = 'c.audio_duration >= ?';
            $params[] = (int) $filters['min_duration'];
        }
        if (isset($filters['max_duration']) && $filters['max_duration'] !== '') {
            $where[] = 'c.audio_duration <= ?';
            $params[] = (int) $filters['max_duration'];
        }
        if (!empty($filters['sentiment'])) {
            $where[] = 'a.overall_sentiment = ?';
            $params[] = $filters['sentiment'];
        }
        $sql = 'SELECT a.overall_sentiment AS s, COUNT(*) AS cnt FROM analyses a ' . $join . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY a.overall_sentiment';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[$row['s']] = (int) $row['cnt'];
        }
        return $out;
    }

    /**
     * @return array<string, float|int|null>
     */
    public static function aggregateKpis(array $filters = []): array
    {
        $join = 'JOIN calls c ON c.id = a.call_id';
        $where = ["c.status = 'complete'"];
        $params = [];
        $day = Call::effectiveCallDayExpr();
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
        if (isset($filters['min_duration']) && $filters['min_duration'] !== '') {
            $where[] = 'c.audio_duration >= ?';
            $params[] = (int) $filters['min_duration'];
        }
        if (isset($filters['max_duration']) && $filters['max_duration'] !== '') {
            $where[] = 'c.audio_duration <= ?';
            $params[] = (int) $filters['max_duration'];
        }
        if (!empty($filters['sentiment'])) {
            $where[] = 'a.overall_sentiment = ?';
            $params[] = $filters['sentiment'];
        }
        $sql = 'SELECT
                    AVG(a.sentiment_score) AS avg_sentiment,
                    AVG(a.agent_confidence_score) AS avg_confidence,
                    COUNT(*) AS analyzed_calls
                FROM analyses a ' . $join . ' WHERE ' . implode(' AND ', $where);
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $followSql = 'SELECT a.follow_up_actions FROM analyses a ' . $join . ' WHERE ' . implode(' AND ', $where);
        $fStmt = Connection::pdo()->prepare($followSql);
        $fStmt->execute($params);
        $pending = 0;
        while ($fr = $fStmt->fetch(PDO::FETCH_ASSOC)) {
            $actions = json_decode((string) $fr['follow_up_actions'], true);
            if (!is_array($actions)) {
                continue;
            }
            foreach ($actions as $act) {
                if (is_array($act) && empty($act['completed'])) {
                    $pending++;
                }
            }
        }

        $wherePlay = $where;
        $wherePlay[] = 'a.sales_question_coverage IS NOT NULL';
        $wherePlay[] = "JSON_TYPE(a.sales_question_coverage) = 'ARRAY'";
        $wherePlay[] = 'JSON_LENGTH(a.sales_question_coverage) > 0';
        $playSql = 'SELECT a.sales_question_coverage FROM analyses a ' . $join . ' WHERE ' . implode(' AND ', $wherePlay);
        $pStmt = Connection::pdo()->prepare($playSql);
        $pStmt->execute($params);
        $playScores = [];
        while ($pr = $pStmt->fetch(PDO::FETCH_ASSOC)) {
            $cov = json_decode((string) $pr['sales_question_coverage'], true);
            if (!is_array($cov) || $cov === []) {
                continue;
            }
            $playScores[] = SalesPlaybookService::scoreCoverage($cov);
        }
        $avgPlaybook = $playScores === [] ? null : round(array_sum($playScores) / count($playScores), 1);

        return [
            'avg_sentiment' => isset($row['avg_sentiment']) ? (float) $row['avg_sentiment'] : null,
            'avg_confidence' => isset($row['avg_confidence']) ? (float) $row['avg_confidence'] : null,
            'analyzed_calls' => (int) ($row['analyzed_calls'] ?? 0),
            'pending_followups' => $pending,
            'avg_playbook_coverage_pct' => $avgPlaybook,
        ];
    }

    public static function updateFollowUpsJson(int $callId, string $json): void
    {
        $stmt = Connection::pdo()->prepare('UPDATE analyses SET follow_up_actions = ? WHERE call_id = ?');
        $stmt->execute([$json, $callId]);
    }
}
