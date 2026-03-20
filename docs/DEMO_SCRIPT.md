# Demo script (~4 minutes) — Demo & explanation

Use this for **Demo & explanation (5 pts)**. Adjust timings to your slot.

## 0:00 — Problem (30s)

- Sales orgs record calls but rarely **measure** discovery coverage, sentiment trend, or rep consistency.
- CP Prompt-X: upload audio → Whisper → GPT → **dashboard + per-call intelligence + reports**.

## 0:30 — Upload (45s)

- Log in → **Upload**.
- Show **transcription language** (auto vs hint for production multilingual teams).
- Submit; land on call page; mention **background processing** + status polling.

## 1:15 — Call analysis (90s)

- **Hero**: summary, sentiment, playbook %, **detected language**, model name.
- Tabs: **Recording + transcript**, **Performance** (charts), **Playbook & keywords** (Q1–Q15 with evidence), **Follow-ups**.
- **Print report** for managers.

## 2:45 — Team view (45s)

- **Dashboard**: KPIs, keywords, sentiment, timeline.
- **Reports**: agent leaderboard, **language mix**, playbook heatmap, trends — **date range** filters.

## 3:30 — Production & rubric hook (30s)

- Auth, CSRF, rate limits, audio outside public root, `.env` config.
- **Prompt versioning** (`PROMPT_VERSION`) + docs: `docs/PROMPT_ENGINEERING.md`, `docs/HACKATHON_EVALUATION.md`.

## Optional Q&A

- **Why GPT-4o-mini?** Configurable via `.env`; swap model without code changes.
- **Wrong language in Whisper?** User hint dropdown + auto-detect default.
