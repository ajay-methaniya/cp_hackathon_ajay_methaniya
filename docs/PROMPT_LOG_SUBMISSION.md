# Prompt Log Submission (CP Prompt-X)

Generated for project submission from in-repo sources:
- Runtime logs: `storage/logs/app.log`
- GPT prompt source: `app/Services/GPTAnalysisService.php`
- Whisper prompt/behavior source: `app/Services/WhisperService.php`
- Prompt notes: `docs/PROMPT_ENGINEERING.md`

Date generated: 2026-03-20

---

## 1) Active Prompt Version + Models

- Prompt version constant: `2026.03.20`
- GPT model observed in logs: `gpt-4o-mini` (older early run: `gpt-4o`)
- Whisper model configured in code: `whisper-1`

Source:
- `app/Services/GPTAnalysisService.php`
- `storage/logs/app.log`

---

## 2) GPT System Prompt (Current Production Snapshot)

From `GPTAnalysisService::analyzeTranscript()`:

```text
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
```

---

## 3) Prompt Engineering Notes (Design Intent)

Summarized from `docs/PROMPT_ENGINEERING.md`:
- Context layering: domain + fidelity + multilingual + playbook + structured JSON + scoring rubric.
- Runtime traceability: `PROMPT_VERSION` logged with each GPT request.
- Determinism: low temperature (`0.3`) + strict JSON response format.
- Post-processing guards: enum normalization and Q1–Q15 coverage normalization in PHP.

---

## 4) Runtime Prompt/Inference Log Extract

### 4.1 Representative GPT + Whisper pipeline lines

From `storage/logs/app.log`:

```text
[2026-03-20T05:20:24.155263+00:00] cp-promptx.INFO: GPT: chat request {"model":"gpt-4o-mini","prompt_version":"2026.03.20","transcript_chars":3303} []
[2026-03-20T05:36:47.232239+00:00] cp-promptx.INFO: GPT: chat request {"model":"gpt-4o-mini","prompt_version":"2026.03.20","transcript_chars":2045} []
[2026-03-20T05:45:22.967738+00:00] cp-promptx.INFO: GPT: chat request {"model":"gpt-4o-mini","prompt_version":"2026.03.20","transcript_chars":1948} []
[2026-03-20T05:46:28.369130+00:00] cp-promptx.INFO: GPT: chat request {"model":"gpt-4o-mini","prompt_version":"2026.03.20","transcript_chars":1581} []
[2026-03-20T07:08:08.123813+00:00] cp-promptx.INFO: GPT: chat request {"model":"gpt-4o-mini","prompt_version":"2026.03.20","transcript_chars":1394} []
[2026-03-20T07:11:09.057473+00:00] cp-promptx.INFO: GPT: chat request {"model":"gpt-4o-mini","prompt_version":"2026.03.20","transcript_chars":1394} []
```

```text
[2026-03-20T05:20:06.727406+00:00] cp-promptx.INFO: Pipeline: whisper begin {"call_id":7,"bytes":8419770} []
[2026-03-20T05:20:24.148873+00:00] cp-promptx.DEBUG: Whisper: verbose response {"http":200,"bytes":25029} []
[2026-03-20T05:20:24.149857+00:00] cp-promptx.INFO: Pipeline: whisper done {"call_id":7,"text_len":3303,"segments":65} []
[2026-03-20T07:06:33.417548+00:00] cp-promptx.WARNING: Whisper: verbose_json failed, falling back to json {... "code":"invalid_api_key" ...} []
[2026-03-20T07:06:33.980115+00:00] cp-promptx.DEBUG: Whisper: json fallback response {"http":401,"bytes":410} []
```

### 4.2 Call IDs with completed GPT analysis (observed in logs)

- `4, 5, 6, 7, 8, 9, 10, 12, 13`

### 4.3 Notable error events

- Missing PSR-18 client (early setup)
- Invalid API key event for call `11` (later corrected in environment)

---

## 5) Submission Notes

- Prompt versioning is productionized in code and logs (`2026.03.20`).
- Prompt contract is explicit, JSON-structured, and normalized server-side.
- Multilingual handling is present: Whisper language passed to GPT prompt and English-normalized output requirements.

---

## 6) Full Raw Log Source

For full, unfiltered runtime logs, include:
- `storage/logs/app.log`

(This document provides the prompt-focused extract and production prompt snapshot for judging clarity.)

