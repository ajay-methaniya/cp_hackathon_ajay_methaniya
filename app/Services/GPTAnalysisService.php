<?php

declare(strict_types=1);

namespace App\Services;

use OpenAI;

/**
 * GPT-4o structured JSON analysis of call transcripts.
 *
 * Bump {@see self::PROMPT_VERSION} when the system prompt contract changes (logs + docs).
 */
final class GPTAnalysisService
{
    /** @var string Semantic version for hackathon traceability / judging (prompt quality). */
    public const PROMPT_VERSION = '2026.03.20';

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('openai.api_key', '');
        $this->model = (string) config('openai.gpt_model', 'gpt-4o-mini');
    }

    /**
     * @param string $detectedLanguage ISO 639-1 from Whisper (e.g. en, es, hi). Analysis strings are normalized to English for the UI.
     * @return array<string, mixed>
     */
    public function analyzeTranscript(string $transcriptText, ?int $approxDurationSeconds = null, string $detectedLanguage = 'en'): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        app_log('info', 'GPT: chat request', [
            'model' => $this->model,
            'prompt_version' => self::PROMPT_VERSION,
            'transcript_chars' => strlen($transcriptText),
        ]);

        $playbookBlock = SalesPlaybookService::promptBlock();

        $systemPrompt = <<<PROMPT
You are an expert sales call analyst AI. The call context is **kitchen cabinet / remodeling sales** (discovery through objection handling).

**Fidelity (required):** Base every judgment only on the transcript. Do not invent customer names, numbers, promises, or quotes that are not clearly supported. If the transcript is very short, noisy, or mostly unintelligible, still return valid JSON: use neutral scores, explain limitations briefly in **summary**, and prefer **unclear** for playbook lines you cannot verify.

**Multilingual (production):** Whisper detected primary language code: **{$detectedLanguage}**. The transcript may be in any language, dialect, or code-switched. Write **summary**, **key_topics**, **positive_observations**, **negative_observations**, **follow_up_actions** (action text), and **paraphrased** playbook reasoning in **English** for a global analytics product. In **evidence_excerpt**, you may quote **verbatim** in the original language when it adds clarity.

Analyze the provided transcript and return ONLY a valid JSON object — no markdown, no explanation, no preamble.

**Common Sales Question Library** — For each line below, decide if the salesperson meaningfully addressed that topic (asked, confirmed, or discussed related substance). Paraphrases count. Use status:
- "covered" = clearly addressed
- "partial" = touched briefly or incomplete
- "not_addressed" = missing
- "unclear" = ambiguous from transcript

{$playbookBlock}

Return this exact JSON structure (all keys required; arrays may be empty only where noted):
{
  "summary": "2-3 sentence executive summary of the call",
  "overall_sentiment": "positive|neutral|negative",
  "sentiment_score": 0.75,
  "agent_confidence_score": 82.5,
  "agent_liveness_pct": 65.0,
  "previous_handling_score": 71.0,
  "sentiment_evolution": [
    {"time_seconds": 0, "score": 0.1, "excerpt": "opening line excerpt"},
    {"time_seconds": 60, "score": 0.4, "excerpt": "..."}
  ],
  "key_topics": ["pricing", "layout", "timeline"],
  "budget_discussed": true,
  "related_project": true,
  "business_strategy": false,
  "marketing_discussed": false,
  "keywords_discussed": [
    {"word": "budget", "count": 4, "category": "Financial"},
    {"word": "shaker", "count": 3, "category": "Product"}
  ],
  "sales_question_coverage": [
    {"question_id": "Q1", "status": "covered", "confidence": 90, "evidence_excerpt": "short quote or null"}
  ],
  "follow_up_actions": [
    {"action": "Send pricing proposal by Friday", "priority": "High", "owner": "Agent"}
  ],
  "positive_observations": ["..."],
  "negative_observations": ["..."]
}

**sales_question_coverage:** Include **exactly one object per question_id from Q1 through Q15**, in order Q1..Q15. Each object must have question_id, status, confidence (0-100), evidence_excerpt (string or null).

Scoring guidelines:
- sentiment_score: -1.0 (very negative) to +1.0 (very positive)
- agent_confidence_score: 0-100 (clarity, pace, assertiveness, objection handling)
- agent_liveness_pct: 0-100 (% of call where agent was actively engaged)
- previous_handling_score: 0-100 (handling prior context / follow-up from earlier touchpoints)
- sentiment_evolution: sample ~every 60s of call, minimum 5 points (if duration is under ~60s, use fewer points spaced across the available time, still ≥3 if any speech exists)
- keywords: top 10-15 significant terms for this industry; **count** should reflect apparent emphasis in the transcript, approximate if exact counts are impractical
PROMPT;

        $client = OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->make();

        $started = (int) (microtime(true) * 1000);

        $userContent = "Transcript language (Whisper): {$detectedLanguage}\n\nCall transcript:\n\n" . $transcriptText;
        if ($approxDurationSeconds !== null) {
            $userContent .= "\n\nApproximate audio duration seconds: " . $approxDurationSeconds;
        }

        $response = $client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
        ]);

        $durationMs = (int) (microtime(true) * 1000) - $started;

        $content = $response->choices[0]->message->content ?? '{}';
        /** @var array<string, mixed> $parsed */
        $parsed = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);

        $usage = $response->usage;
        $tokens = $usage?->totalTokens;

        $parsed['gpt_model_used'] = $this->model;
        $parsed['tokens_used'] = $tokens;
        $parsed['analysis_duration_ms'] = $durationMs;

        return $this->normalizeForStorage($parsed);
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, mixed>
     */
    private function normalizeForStorage(array $parsed): array
    {
        $sent = $parsed['overall_sentiment'] ?? 'neutral';
        if (!in_array($sent, ['positive', 'neutral', 'negative'], true)) {
            $sent = 'neutral';
        }

        $coverage = SalesPlaybookService::normalizeCoverage($parsed['sales_question_coverage'] ?? []);

        return [
            'call_summary' => (string) ($parsed['summary'] ?? ''),
            'overall_sentiment' => $sent,
            'sentiment_score' => isset($parsed['sentiment_score']) ? (float) $parsed['sentiment_score'] : null,
            'agent_confidence_score' => isset($parsed['agent_confidence_score']) ? (float) $parsed['agent_confidence_score'] : null,
            'agent_liveness_pct' => isset($parsed['agent_liveness_pct']) ? (float) $parsed['agent_liveness_pct'] : null,
            'previous_handling_score' => isset($parsed['previous_handling_score']) ? (float) $parsed['previous_handling_score'] : null,
            'sentiment_evolution' => is_array($parsed['sentiment_evolution'] ?? null) ? $parsed['sentiment_evolution'] : [],
            'key_topics' => is_array($parsed['key_topics'] ?? null) ? $parsed['key_topics'] : [],
            'budget_discussed' => !empty($parsed['budget_discussed']),
            'related_project' => !empty($parsed['related_project']),
            'business_strategy' => !empty($parsed['business_strategy']),
            'marketing_discussed' => !empty($parsed['marketing_discussed']),
            'keywords_discussed' => is_array($parsed['keywords_discussed'] ?? null) ? $parsed['keywords_discussed'] : [],
            'sales_question_coverage' => $coverage,
            'sales_coverage_score_pct' => SalesPlaybookService::scoreCoverage($coverage),
            'follow_up_actions' => $this->normalizeFollowUps($parsed['follow_up_actions'] ?? []),
            'positive_observations' => is_array($parsed['positive_observations'] ?? null) ? $parsed['positive_observations'] : [],
            'negative_observations' => is_array($parsed['negative_observations'] ?? null) ? $parsed['negative_observations'] : [],
            'gpt_model_used' => (string) ($parsed['gpt_model_used'] ?? $this->model),
            'tokens_used' => isset($parsed['tokens_used']) ? (int) $parsed['tokens_used'] : null,
            'analysis_duration_ms' => isset($parsed['analysis_duration_ms']) ? (int) $parsed['analysis_duration_ms'] : null,
        ];
    }

    /**
     * @param mixed $raw
     * @return list<array<string, mixed>>
     */
    private function normalizeFollowUps(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = [
                'action' => (string) ($item['action'] ?? ''),
                'priority' => (string) ($item['priority'] ?? 'Medium'),
                'owner' => (string) ($item['owner'] ?? 'Agent'),
                'completed' => !empty($item['completed']),
            ];
        }
        return $out;
    }
}
