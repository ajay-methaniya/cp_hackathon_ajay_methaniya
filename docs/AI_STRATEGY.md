# AI utilization strategy

How this product uses OpenAI end-to-end (relevant for **AI utilization strategy** judging).

## Pipeline

1. **Whisper** (`whisper-1`) — Speech-to-text; detects **language**; optional **ISO 639-1 hint** for difficult audio.
2. **GPT** (configurable chat model) — Single structured pass: summary, sentiment, scores, keywords, **Q1–Q15 playbook coverage**, follow-ups, observations.
3. **No embedding / RAG in v1** — Keeps latency and cost predictable for a hackathon demo; transcripts are stored for future RAG if needed.

## Why not one giant prompt file?

- Domain, fidelity, multilingual rules, and playbook text are **composed** in `GPTAnalysisService` from `SalesPlaybookService::promptBlock()` so the **question library** stays data-driven (`config/sales_questions.php`).

## Operational choices

- **JSON mode** for machine-parseable analytics.
- **Server-side normalization** so the UI never depends on a single flaky array shape from the model.
- **Caching** on dashboard and reports aggregates (file cache, TTL in config) to reduce repeated GPT calls — analysis runs **once per call** at upload time.

## Development workflow (Cursor / AI-assisted coding)

- Project rules in **`.cursorrules`** (PDO, CSRF, thin controllers).
- Iteration on prompts documented in **`docs/PROMPT_ENGINEERING.md`** with version bumps.
