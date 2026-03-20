<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Connection;
use PDO;

final class Transcript
{
    /**
     * @param array<string, mixed> $segments Will be JSON-encoded
     */
    public static function upsert(int $callId, string $rawText, array $segments, string $language = 'en'): void
    {
        $json = json_encode($segments, JSON_THROW_ON_ERROR);
        $stmt = Connection::pdo()->prepare(
            'INSERT INTO transcripts (call_id, raw_text, segments, language)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE raw_text = VALUES(raw_text), segments = VALUES(segments), language = VALUES(language)'
        );
        $stmt->execute([$callId, $rawText, $json, $language]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forCall(int $callId): ?array
    {
        $stmt = Connection::pdo()->prepare('SELECT * FROM transcripts WHERE call_id = ? LIMIT 1');
        $stmt->execute([$callId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
