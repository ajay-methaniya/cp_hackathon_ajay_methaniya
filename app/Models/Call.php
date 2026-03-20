<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

final class Call
{
    /**
     * Calendar day for dashboard/list filters when call_date is NULL (matches UI: call_date ?? created_at date).
     */
    public static function effectiveCallDayExpr(): string
    {
        return 'COALESCE(c.call_date, DATE(c.created_at))';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $stmt = Connection::pdo()->prepare(
            'INSERT INTO calls (
                user_id, title, audio_file_path, audio_duration, audio_format, file_size_bytes,
                status, contact_name, contact_role, contact_tenure, call_date, whisper_language_hint
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['audio_file_path'],
            $data['audio_duration'] ?? null,
            $data['audio_format'] ?? null,
            $data['file_size_bytes'] ?? null,
            $data['status'] ?? 'uploaded',
            $data['contact_name'] ?? null,
            $data['contact_role'] ?? null,
            $data['contact_tenure'] ?? null,
            $data['call_date'] ?? null,
            $data['whisper_language_hint'] ?? null,
        ]);
        return (int) Connection::pdo()->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $stmt = Connection::pdo()->prepare(
            'SELECT c.*, u.name AS agent_name, u.email AS agent_email
             FROM calls c
             JOIN users u ON u.id = c.user_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Connection::pdo()->prepare('UPDATE calls SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    /**
     * Store a short failure reason for the UI / logs (cleared on success paths manually).
     */
    public static function setLastError(int $id, ?string $message): void
    {
        $msg = $message === null ? null : substr($message, 0, 4000);
        $stmt = Connection::pdo()->prepare('UPDATE calls SET last_error = ? WHERE id = ?');
        $stmt->execute([$msg, $id]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function update(int $id, array $fields): void
    {
        $allowed = ['audio_duration', 'audio_format', 'file_size_bytes', 'status', 'last_error', 'title', 'contact_name', 'contact_role', 'contact_tenure', 'call_date', 'user_id'];
        $sets = [];
        $params = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[] = "$k = ?";
                $params[] = $v;
            }
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $sql = 'UPDATE calls SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function recentWithMeta(int $limit = 10, array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $day = self::effectiveCallDayExpr();
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

        $joinAnalysis = !empty($filters['sentiment']) ? 'INNER JOIN analyses a ON a.call_id = c.id' : 'LEFT JOIN analyses a ON a.call_id = c.id';
        $sql = 'SELECT c.*, u.name AS agent_name, a.overall_sentiment
                FROM calls c
                JOIN users u ON u.id = c.user_id
                ' . $joinAnalysis . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.created_at DESC
                LIMIT ' . (int) $limit;

        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{where: list<string>, params: list<mixed>, joinAnalysis: string}
     */
    private static function listQueryParts(array $filters, ?string $search): array
    {
        $where = ['1=1'];
        $params = [];

        $day = self::effectiveCallDayExpr();
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

        if ($search !== null && $search !== '') {
            $like = '%' . self::escapeLike($search) . '%';
            $where[] = '(c.title LIKE ? OR IFNULL(c.contact_name, \'\') LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $joinAnalysis = !empty($filters['sentiment']) ? 'INNER JOIN analyses a ON a.call_id = c.id' : 'LEFT JOIN analyses a ON a.call_id = c.id';

        return ['where' => $where, 'params' => $params, 'joinAnalysis' => $joinAnalysis];
    }

    private static function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $s);
    }

    /**
     * Whitelist map: request key => SQL ORDER BY expression (with table aliases c, u, a).
     *
     * @return array<string, string>
     */
    private static function listSortColumns(): array
    {
        $day = self::effectiveCallDayExpr();

        return [
            'created_at' => 'c.created_at',
            'title' => 'c.title',
            'agent' => 'u.name',
            'duration' => 'c.audio_duration',
            'call_date' => $day,
            'status' => 'c.status',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function allForList(array $filters = []): array
    {
        $parts = self::listQueryParts($filters, null);
        $sql = 'SELECT c.*, u.name AS agent_name, a.overall_sentiment
                FROM calls c
                JOIN users u ON u.id = c.user_id
                ' . $parts['joinAnalysis'] . '
                WHERE ' . implode(' AND ', $parts['where']) . '
                ORDER BY c.created_at DESC';

        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($parts['params']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginated calls list with search and sort (for /calls UI).
     *
     * @param array<string, mixed> $filters date_from, date_to, agent_id, sentiment, min/max duration
     * @param array{q?: string, sort?: string, dir?: string, page?: int, per_page?: int} $opts
     * @return array{rows: list<array<string,mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function paginatedForList(array $filters, array $opts): array
    {
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $parts = self::listQueryParts($filters, $q !== '' ? $q : null);

        $sortKey = (string) ($opts['sort'] ?? 'created_at');
        $sortMap = self::listSortColumns();
        $orderCol = $sortMap[$sortKey] ?? $sortMap['created_at'];
        $dir = strtolower((string) ($opts['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $page = max(1, (int) ($opts['page'] ?? 1));
        $perPage = (int) ($opts['per_page'] ?? 25);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 25;
        }

        $from = 'calls c
                JOIN users u ON u.id = c.user_id
                ' . $parts['joinAnalysis'];

        $whereSql = implode(' AND ', $parts['where']);
        $countSql = 'SELECT COUNT(DISTINCT c.id) FROM ' . $from . ' WHERE ' . $whereSql;
        $stmt = Connection::pdo()->prepare($countSql);
        $stmt->execute($parts['params']);
        $total = (int) $stmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $nullsLast = '';
        if ($sortKey === 'duration' || $sortKey === 'call_date') {
            $nullsLast = ' ' . $orderCol . ' IS NULL ASC,';
        }

        $selectSql = 'SELECT c.*, u.name AS agent_name, a.overall_sentiment
                FROM ' . $from . '
                WHERE ' . $whereSql . '
                ORDER BY' . $nullsLast . ' ' . $orderCol . ' ' . $dir . ', c.id DESC
                LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

        $stmt = Connection::pdo()->prepare($selectSql);
        $stmt->execute($parts['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public static function delete(int $id): void
    {
        $stmt = Connection::pdo()->prepare('DELETE FROM calls WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Total calls matching optional filters (any status).
     */
    public static function countTotal(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];
        $table = 'calls c';
        $join = '';
        if (!empty($filters['sentiment'])) {
            $join = ' INNER JOIN analyses a ON a.call_id = c.id ';
            $where[] = 'a.overall_sentiment = ?';
            $params[] = $filters['sentiment'];
        }
        $day = self::effectiveCallDayExpr();
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
        $sql = 'SELECT COUNT(DISTINCT c.id) FROM ' . $table . $join . ' WHERE ' . implode(' AND ', $where);
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    public static function countsByStatus(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        $day = 'COALESCE(call_date, DATE(created_at))';
        if (!empty($filters['date_from'])) {
            $where[] = $day . ' >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = $day . ' <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'user_id = ?';
            $params[] = (int) $filters['agent_id'];
        }

        $sql = 'SELECT status, COUNT(*) AS c FROM calls WHERE ' . implode(' AND ', $where) . ' GROUP BY status';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[$row['status']] = (int) $row['c'];
        }
        return $out;
    }

    /**
     * Timeline: calls per calendar day (uses call_date, falls back to created_at day).
     * Fills every day in the selected range (or last N calendar days) with 0 when missing.
     *
     * @return list<array{day:string,count:int}>
     */
    public static function timelineLastDays(int $days = 30, array $filters = []): array
    {
        $dayExpr = 'COALESCE(c.call_date, DATE(c.created_at))';
        $join = '';
        $where = [];
        $params = [];

        if (!empty($filters['sentiment'])) {
            $join = ' INNER JOIN analyses a ON a.call_id = c.id ';
            $where[] = 'a.overall_sentiment = ?';
            $params[] = $filters['sentiment'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = $dayExpr . ' >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = $dayExpr . ' <= ?';
            $params[] = $filters['date_to'];
        }
        if ($where === [] || empty($filters['date_from']) || empty($filters['date_to'])) {
            $where[] = $dayExpr . ' >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
            $params[] = $days;
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

        $sql = 'SELECT ' . $dayExpr . ' AS day, COUNT(DISTINCT c.id) AS cnt
                FROM calls c ' . $join . '
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY ' . $dayExpr . '
                ORDER BY day ASC';
        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts = [];
        foreach ($rows as $r) {
            $d = (string) $r['day'];
            if ($d !== '') {
                $counts[$d] = (int) $r['cnt'];
            }
        }

        $rangeStart = null;
        $rangeEnd = null;
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            try {
                $rangeStart = new \DateTimeImmutable((string) $filters['date_from']);
                $rangeEnd = new \DateTimeImmutable((string) $filters['date_to']);
            } catch (\Throwable) {
                $rangeStart = $rangeEnd = null;
            }
        }
        if ($rangeStart === null || $rangeEnd === null) {
            $rangeEnd = new \DateTimeImmutable('today');
            $rangeStart = $rangeEnd->modify('-' . max(1, $days) . ' days');
        }
        if ($rangeStart > $rangeEnd) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        $maxSpan = 120;
        $span = (int) $rangeStart->diff($rangeEnd)->days + 1;
        if ($span > $maxSpan) {
            $rangeStart = $rangeEnd->modify('-' . ($maxSpan - 1) . ' days');
        }

        $out = [];
        for ($d = $rangeStart; $d <= $rangeEnd; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $out[] = ['day' => $key, 'count' => $counts[$key] ?? 0];
        }

        return $out;
    }
}
