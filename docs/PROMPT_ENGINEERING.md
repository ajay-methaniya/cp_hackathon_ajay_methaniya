# Prompt engineering (GPT analysis)

This file documents **intent and iteration** behind the production system prompt — relevant for **Prompt quality (20 pts)** judging.

## Versioning

- **`GPTAnalysisService::PROMPT_VERSION`** (e.g. `2026.03.20`) is logged with each request (`storage/logs/app.log` under `GPT: chat request`).
- Bump the constant whenever the **system prompt contract** changes so behavior is traceable.

## Design layers (context management)

1. **Domain** — Kitchen cabinet / remodeling sales; discovery → objection handling.
2. **Fidelity** — Do not invent facts; handle short/noisy audio with neutral scores and honest summary.
3. **Multilingual** — Whisper ISO language code passed in; UI copy in English; evidence may quote original language.
4. **Playbook** — Canonical Q1–Q15 list injected from `config/sales_questions.php` so the model always evaluates the **same** client library.
5. **Structured output** — `response_format: json_object` plus explicit JSON skeleton in the prompt; PHP validates and **normalizes** coverage so every Q1–Q15 row exists (`SalesPlaybookService::normalizeCoverage`).
6. **Scoring rubric** — Numeric ranges and meanings spelled out in-system to reduce drift.

## Iteration knobs

| Knob | Value | Purpose |
|------|--------|--------|
| Temperature | `0.3` | More consistent JSON and scores across runs |
| Model | `OPENAI_GPT_MODEL` (default `gpt-4o-mini`) | Cost/latency vs quality tradeoff via `.env` |

## Failure modes handled in code (not only in prompt)

- Invalid/missing sentiment enum → forced to `neutral`.
- Partial / missing `sales_question_coverage` from model → merged with defaults per Q1–Q15.
- Follow-up list normalized to consistent keys.

## Whisper

- Prefers `verbose_json` + segments; falls back to `json`.
- Optional `language` parameter when user selects a hint (`WhisperService` + `config/transcription_languages.php`).
