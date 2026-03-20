<?php

declare(strict_types=1);

namespace App\Services;

/**
 * OpenAI Whisper transcription via REST (verbose_json + segments, with fallback).
 *
 * Language hints and multilingual behavior are documented in docs/PROMPT_ENGINEERING.md
 * and docs/AI_STRATEGY.md (hackathon: prompt quality / AI utilization).
 */
final class WhisperService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('openai.api_key', '');
        $this->model = (string) config('openai.whisper_model', 'whisper-1');
    }

    /**
     * @param string|null $languageHint ISO 639-1 (2 letters) — omit or null for auto-detect (multilingual / mixed).
     * @return array{text:string,segments:list<array<string,mixed>>,language:string,duration:?float}
     */
    public function transcribe(string $audioFilePath, ?string $languageHint = null): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        try {
            return $this->requestVerbose($audioFilePath, $languageHint);
        } catch (\Throwable $e) {
            app_log('warning', 'Whisper: verbose_json failed, falling back to json', [
                'message' => $e->getMessage(),
                'file' => basename($audioFilePath),
            ]);

            return $this->requestSimpleJson($audioFilePath, $languageHint);
        }
    }

    /**
     * verbose_json + segment timestamps (preferred).
     *
     * @return array{text:string,segments:list<array<string,mixed>>,language:string,duration:?float}
     */
    private function requestVerbose(string $audioFilePath, ?string $languageHint): array
    {
        $fields = [
            'response_format' => 'verbose_json',
            'timestamp_granularities[]' => 'segment',
        ];
        $response = $this->curlTranscribe($audioFilePath, $fields, $languageHint);
        $httpCode = $response['code'];
        $body = $response['body'];
        $curlErr = $response['curl_error'];

        app_log('debug', 'Whisper: verbose response', ['http' => $httpCode, 'bytes' => strlen($body)]);

        if ($body === false || $body === '') {
            throw new \RuntimeException('Whisper: empty response. cURL: ' . $curlErr);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Whisper API HTTP ' . $httpCode . ': ' . mb_substr((string) $body, 0, 800));
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Whisper: invalid JSON — ' . mb_substr((string) $body, 0, 200));
        }

        return [
            'text' => (string) ($data['text'] ?? ''),
            'segments' => is_array($data['segments'] ?? null) ? $data['segments'] : [],
            'language' => (string) ($data['language'] ?? 'en'),
            'duration' => isset($data['duration']) ? (float) $data['duration'] : null,
        ];
    }

    /**
     * @return array{text:string,segments:list<array<string,mixed>>,language:string,duration:?float}
     */
    private function requestSimpleJson(string $audioFilePath, ?string $languageHint): array
    {
        $response = $this->curlTranscribe($audioFilePath, ['response_format' => 'json'], $languageHint);
        $httpCode = $response['code'];
        $body = $response['body'];
        $curlErr = $response['curl_error'];

        app_log('debug', 'Whisper: json fallback response', ['http' => $httpCode, 'bytes' => strlen((string) $body)]);

        if ($body === false || $body === '') {
            throw new \RuntimeException('Whisper (fallback): empty response. cURL: ' . $curlErr);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Whisper (fallback) HTTP ' . $httpCode . ': ' . mb_substr((string) $body, 0, 800));
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Whisper (fallback): invalid JSON — ' . mb_substr((string) $body, 0, 200));
        }

        return [
            'text' => (string) ($data['text'] ?? ''),
            'segments' => [],
            'language' => (string) ($data['language'] ?? 'en'),
            'duration' => isset($data['duration']) ? (float) $data['duration'] : null,
        ];
    }

    /**
     * @param array<string, string> $extraFields
     * @return array{code:int,body:string|bool,curl_error:string}
     */
    private function curlTranscribe(string $audioFilePath, array $extraFields, ?string $languageHint): array
    {
        $curl = curl_init();

        $mime = mime_content_type($audioFilePath) ?: 'application/octet-stream';
        $cfile = new \CURLFile($audioFilePath, $mime, basename($audioFilePath));

        $postFields = array_merge([
            'file' => $cfile,
            'model' => $this->model,
        ], $extraFields);

        $hint = self::normalizeLanguageHint($languageHint);
        if ($hint !== null) {
            $postFields['language'] = $hint;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        return [
            'code' => $httpCode,
            'body' => $response,
            'curl_error' => $err,
        ];
    }

    /**
     * Whisper expects ISO 639-1 (typically 2 letters). Returns null for auto-detect.
     */
    public static function normalizeLanguageHint(?string $hint): ?string
    {
        if ($hint === null || $hint === '') {
            return null;
        }
        $h = strtolower(trim($hint));
        if (!preg_match('/^[a-z]{2,3}$/', $h)) {
            return null;
        }
        if (strlen($h) === 3) {
            /** @var array<string, string> */
            $map = [
                'fil' => 'tl',
            ];
            $h = $map[$h] ?? substr($h, 0, 2);
        }

        return $h;
    }
}
