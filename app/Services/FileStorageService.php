<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validates and stores uploaded audio outside the public web root.
 */
final class FileStorageService
{
    private const ALLOWED_MIMES = [
        'audio/mpeg',
        'audio/wav',
        'audio/wave',
        'audio/x-wav',
        'audio/vnd.wave',
        'audio/x-ms-wav',
        'audio/mp4',
        'audio/x-m4a',
        'audio/m4a',
        'audio/ogg',
        'audio/webm',
    ];

    /**
     * @param array<string, mixed> $files Typically the full $_FILES superglobal
     * @return array{path:string, extension:string, size:int, mime:string}
     */
    public function storeUploadedAudio(array $files, string $fieldName = 'audio'): array
    {
        $file = $files[$fieldName] ?? null;
        if (!is_array($file)) {
            $up = ini_get('upload_max_filesize') ?: '?';
            $post = ini_get('post_max_size') ?: '?';
            throw new \RuntimeException(
                'No audio file was received by PHP. The total POST body (file + form + multipart overhead) '
                . "must be under post_max_size (yours: {$post}). A file slightly over 8 MB often fails when PHP’s default is 8M. "
                . "upload_max_filesize is {$up}. Set both to at least 100M in public/.user.ini or php.ini, then restart Apache or PHP-FPM."
            );
        }

        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage($err));
        }

        $max = (int) config('storage.max_upload_bytes', 100 * 1024 * 1024);
        if (($file['size'] ?? 0) > $max) {
            throw new \RuntimeException('File exceeds maximum upload size.');
        }

        $tmp = (string) $file['tmp_name'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \RuntimeException('Invalid audio file type.');
        }

        $orig = (string) ($file['name'] ?? 'audio');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowedExt = config('storage.allowed_extensions', []);
        if (!in_array($ext, $allowedExt, true)) {
            throw new \RuntimeException('Invalid file extension.');
        }

        $dir = (string) config('storage.audio_disk_path');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create storage directory.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new \RuntimeException('Could not save uploaded file.');
        }

        return [
            'path' => $dest,
            'extension' => $ext,
            'size' => (int) $file['size'],
            'mime' => $mime,
        ];
    }

    public function deleteIfExists(string $absolutePath): void
    {
        if ($absolutePath !== '' && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'File is larger than PHP upload_max_filesize. Increase upload_max_filesize and post_max_size in php.ini (e.g. 100M).',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum size allowed by the form.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder for uploads (upload_tmp_dir).',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Upload failed (error code ' . $code . ').',
        };
    }
}
