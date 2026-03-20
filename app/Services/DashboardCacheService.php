<?php

declare(strict_types=1);

namespace App\Services;

/**
 * File-based JSON cache with TTL for dashboard aggregates.
 */
final class DashboardCacheService
{
    private string $dir;
    private int $ttl;

    public function __construct()
    {
        $this->dir = (string) config('storage.cache_path');
        $this->ttl = (int) config('storage.cache_ttl_seconds', 900);
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function key(string $suffix, array $filters = []): string
    {
        return 'dash_v3_' . $suffix . '_' . md5(json_encode($filters, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $path = $this->dir . '/' . $key . '.json';
        if (!is_file($path)) {
            return null;
        }
        if (time() - filemtime($path) > $this->ttl) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function set(string $key, array $payload): void
    {
        $path = $this->dir . '/' . $key . '.json';
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX);
    }
}
