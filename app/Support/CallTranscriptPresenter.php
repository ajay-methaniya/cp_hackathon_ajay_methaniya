<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builds structured transcript data for the call detail UI (segments + speaker parsing fallback).
 */
final class CallTranscriptPresenter
{
    /**
     * @param array<string, mixed>|null $transcript Row from transcripts table (raw_text, segments array)
     * @return list<array{start:?float,end:?float,text:string,speaker:int,label:string}>
     */
    public static function blocks(?array $transcript): array
    {
        if (!is_array($transcript)) {
            return [];
        }
        $segments = $transcript['segments'] ?? [];
        if (is_array($segments) && $segments !== []) {
            $out = [];
            foreach ($segments as $i => $seg) {
                if (!is_array($seg)) {
                    continue;
                }
                $text = trim((string) ($seg['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $start = isset($seg['start']) && is_numeric($seg['start']) ? (float) $seg['start'] : null;
                $end = isset($seg['end']) && is_numeric($seg['end']) ? (float) $seg['end'] : null;
                $out[] = [
                    'start' => $start,
                    'end' => $end,
                    'text' => $text,
                    'speaker' => $i % 2,
                    'label' => self::fmtTime($start),
                ];
            }

            return $out;
        }

        return self::parseRawSpeakers((string) ($transcript['raw_text'] ?? ''));
    }

    /**
     * @return list<array{start:?float,end:?float,text:string,speaker:int,label:string}>
     */
    private static function parseRawSpeakers(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $blocks = [];
        $currentLabel = 'Transcript';
        $buf = [];

        $flush = static function () use (&$blocks, &$buf, &$currentLabel): void {
            $t = trim(implode("\n", $buf));
            if ($t !== '') {
                $blocks[] = [
                    'start' => null,
                    'end' => null,
                    'text' => $t,
                    'speaker' => 0,
                    'label' => $currentLabel,
                ];
            }
            $buf = [];
        };

        foreach ($lines as $line) {
            if (preg_match('/^([^\n:]{1,48}):\s*(.*)$/u', $line, $m)) {
                $flush();
                $currentLabel = trim($m[1]);
                $buf[] = $m[2];
            } else {
                $buf[] = $line;
            }
        }
        $flush();

        if ($blocks === []) {
            return [[
                'start' => null,
                'end' => null,
                'text' => $raw,
                'speaker' => 0,
                'label' => 'Transcript',
            ]];
        }

        foreach ($blocks as $i => $_) {
            $blocks[$i]['speaker'] = $i % 2;
        }

        return $blocks;
    }

    private static function fmtTime(?float $sec): string
    {
        if ($sec === null || $sec < 0) {
            return '';
        }
        $m = (int) floor($sec / 60);
        $s = (int) floor(fmod($sec, 60.0));

        return sprintf('%d:%02d', $m, $s);
    }
}
