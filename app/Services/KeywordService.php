<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Models\Call;
use PDO;

/**
 * Aggregates keyword frequencies across completed analyses.
 */
final class KeywordService
{
    /**
     * @param array<string, mixed> $filters
     * @return list<array{word:string,count:int,category:string}>
     */
    public function topKeywords(int $limit = 10, array $filters = []): array
    {
        $join = 'JOIN calls c ON c.id = a.call_id';
        $where = ["c.status = 'complete'", 'a.keywords_discussed IS NOT NULL'];
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

        $sql = 'SELECT a.keywords_discussed FROM analyses a ' . $join . ' WHERE ' . implode(' AND ', $where);
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);

        $merged = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $arr = json_decode((string) $row['keywords_discussed'], true);
            if (!is_array($arr)) {
                continue;
            }
            foreach ($arr as $kw) {
                if (!is_array($kw)) {
                    continue;
                }
                $w = strtolower(trim((string) ($kw['word'] ?? '')));
                if ($w === '') {
                    continue;
                }
                $cat = (string) ($kw['category'] ?? 'Process');
                $cnt = (int) ($kw['count'] ?? 1);
                if (!isset($merged[$w])) {
                    $merged[$w] = ['word' => $w, 'count' => 0, 'category' => $cat];
                }
                $merged[$w]['count'] += $cnt;
            }
        }

        usort($merged, static fn (array $a, array $b) => $b['count'] <=> $a['count']);
        return array_slice(array_values($merged), 0, $limit);
    }
}
