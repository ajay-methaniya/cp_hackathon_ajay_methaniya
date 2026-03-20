<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Display helpers for sentiment labels and colors.
 */
final class SentimentService
{
    public static function badgeClass(string $sentiment): string
    {
        return match ($sentiment) {
            'positive' => 'bg-emerald-500/18 text-emerald-200',
            'negative' => 'bg-rose-500/15 text-rose-200',
            default => 'bg-amber-500/14 text-amber-200',
        };
    }

    public static function scoreColor(?float $score): string
    {
        if ($score === null) {
            return 'text-surface-border';
        }
        if ($score >= 0.25) {
            return 'text-positive';
        }
        if ($score <= -0.25) {
            return 'text-negative';
        }
        return 'text-neutral';
    }
}
