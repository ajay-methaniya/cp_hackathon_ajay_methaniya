<?php

declare(strict_types=1);

$maxMb = (int) ($_ENV['MAX_UPLOAD_SIZE_MB'] ?? 100);
$formats = $_ENV['ALLOWED_AUDIO_FORMATS'] ?? 'mp3,mp4,wav,m4a,ogg,webm';

return [
    'max_upload_bytes' => $maxMb * 1024 * 1024,
    'allowed_extensions' => array_map('trim', explode(',', strtolower($formats))),
    'audio_disk_path' => dirname(__DIR__) . '/storage/audio',
    'cache_path' => dirname(__DIR__) . '/storage/cache',
    'logs_path' => dirname(__DIR__) . '/storage/logs',
    'rate_limit_path' => dirname(__DIR__) . '/storage/ratelimit',
    'cache_ttl_seconds' => 900,
];
