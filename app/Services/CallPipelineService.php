<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Analysis;
use App\Models\Call;
use App\Models\Transcript;

/**
 * Runs Whisper transcription then GPT analysis for a call record.
 */
final class CallPipelineService
{
    public function __construct(
        private readonly WhisperService $whisper = new WhisperService(),
        private readonly GPTAnalysisService $gpt = new GPTAnalysisService(),
    ) {
    }

    public function process(int $callId): void
    {
        app_log('info', 'Pipeline: start', ['call_id' => $callId]);

        $call = Call::find($callId);
        if ($call === null) {
            app_log('warning', 'Pipeline: call row missing', ['call_id' => $callId]);

            return;
        }

        $path = (string) $call['audio_file_path'];
        if (!is_file($path)) {
            $msg = 'Audio file missing on disk: ' . $path;
            Call::setLastError($callId, $msg);
            Call::updateStatus($callId, 'failed');
            app_log('error', 'Pipeline: audio missing', ['call_id' => $callId, 'path' => $path]);

            return;
        }

        try {
            Call::setLastError($callId, null);
            Call::updateStatus($callId, 'transcribing');
            app_log('info', 'Pipeline: whisper begin', ['call_id' => $callId, 'bytes' => filesize($path)]);

            $hint = isset($call['whisper_language_hint']) && (string) $call['whisper_language_hint'] !== ''
                ? (string) $call['whisper_language_hint']
                : null;
            $t = $this->whisper->transcribe($path, WhisperService::normalizeLanguageHint($hint));

            app_log('info', 'Pipeline: whisper done', [
                'call_id' => $callId,
                'text_len' => strlen($t['text'] ?? ''),
                'segments' => count($t['segments'] ?? []),
            ]);

            $duration = $t['duration'] !== null ? (int) round($t['duration']) : null;
            if ($duration === null && isset($call['audio_duration'])) {
                $duration = (int) $call['audio_duration'];
            }

            Transcript::upsert($callId, $t['text'], $t['segments'], $t['language']);

            Call::update($callId, [
                'audio_duration' => $duration,
                'status' => 'analyzing',
            ]);

            app_log('info', 'Pipeline: GPT begin', ['call_id' => $callId]);

            $analysis = $this->gpt->analyzeTranscript($t['text'], $duration, (string) ($t['language'] ?? 'en'));

            app_log('info', 'Pipeline: GPT done', ['call_id' => $callId]);

            Analysis::saveForCall($callId, $analysis);

            Call::setLastError($callId, null);
            Call::updateStatus($callId, 'complete');
            app_log('info', 'Pipeline: complete', ['call_id' => $callId]);
        } catch (\Throwable $e) {
            $short = $e->getMessage();
            Call::setLastError($callId, $short);
            Call::updateStatus($callId, 'failed');
            app_log('error', 'Pipeline: failed', [
                'call_id' => $callId,
                'exception' => $e::class,
                'message' => $short,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
