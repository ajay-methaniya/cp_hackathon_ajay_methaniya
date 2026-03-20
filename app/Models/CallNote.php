<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

final class CallNote
{
    public static function create(int $callId, int $userId, string $note): int
    {
        $stmt = Connection::pdo()->prepare(
            'INSERT INTO call_notes (call_id, user_id, note) VALUES (?, ?, ?)'
        );
        $stmt->execute([$callId, $userId, $note]);
        return (int) Connection::pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forCall(int $callId): array
    {
        $stmt = Connection::pdo()->prepare(
            'SELECT n.*, u.name AS author_name
             FROM call_notes n
             JOIN users u ON u.id = n.user_id
             WHERE n.call_id = ?
             ORDER BY n.created_at DESC'
        );
        $stmt->execute([$callId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
