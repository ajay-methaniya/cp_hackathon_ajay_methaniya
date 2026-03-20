<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Kitchen cabinet sales playbook (Q1–Q15) — aligns with hackathon “Common Sales Question Library”.
 */
final class SalesPlaybookService
{
    /**
     * @return list<array{id: string, stage: string, text: string}>
     */
    public static function library(): array
    {
        $q = config('sales_questions', []);
        return is_array($q) ? $q : [];
    }

    public static function questionCount(): int
    {
        return count(self::library());
    }

    /**
     * Bullet list for the GPT system prompt.
     */
    public static function promptBlock(): string
    {
        $lines = [];
        foreach (self::library() as $row) {
            $lines[] = sprintf(
                '- %s [%s] %s',
                $row['id'] ?? '',
                $row['stage'] ?? '',
                $row['text'] ?? ''
            );
        }
        return implode("\n", $lines);
    }

    /**
     * Merge GPT output with the canonical library (all IDs present, stable order).
     *
     * @param list<array<string, mixed>>|mixed $fromGpt
     * @return list<array<string, mixed>>
     */
    public static function normalizeCoverage(mixed $fromGpt): array
    {
        $byId = [];
        if (is_array($fromGpt)) {
            foreach ($fromGpt as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = (string) ($item['question_id'] ?? '');
                if ($id !== '') {
                    $byId[$id] = $item;
                }
            }
        }

        $out = [];
        foreach (self::library() as $def) {
            $id = (string) ($def['id']);
            $existing = $byId[$id] ?? [];
            $status = (string) ($existing['status'] ?? 'not_addressed');
            if (!in_array($status, ['covered', 'partial', 'not_addressed', 'unclear'], true)) {
                $status = 'not_addressed';
            }
            $conf = isset($existing['confidence']) ? (float) $existing['confidence'] : null;
            if ($conf !== null) {
                $conf = max(0.0, min(100.0, $conf));
            }
            $excerpt = $existing['evidence_excerpt'] ?? null;
            $out[] = [
                'question_id' => $id,
                'stage' => (string) $def['stage'],
                'question_text' => (string) $def['text'],
                'status' => $status,
                'confidence' => $conf,
                'evidence_excerpt' => $excerpt !== null && $excerpt !== '' ? (string) $excerpt : null,
            ];
        }

        return $out;
    }

    /**
     * Weighted coverage score 0–100 (partial = 0.5).
     *
     * @param list<array<string, mixed>> $coverage
     */
    public static function scoreCoverage(array $coverage): float
    {
        $n = self::questionCount();
        if ($n === 0) {
            return 0.0;
        }
        $weights = [
            'covered' => 1.0,
            'partial' => 0.5,
            'not_addressed' => 0.0,
            'unclear' => 0.0,
        ];
        $sum = 0.0;
        foreach ($coverage as $row) {
            $st = (string) ($row['status'] ?? 'not_addressed');
            $sum += $weights[$st] ?? 0.0;
        }
        return round(($sum / $n) * 100, 1);
    }
}
