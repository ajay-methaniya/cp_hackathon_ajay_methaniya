# Hackathon evaluation criteria — how CP Prompt-X maps to them

This document ties the official judging rubric (100 points) to **concrete artifacts** in this repository so reviewers can verify intent quickly.

| Criterion | Points | Evidence in this project |
|-----------|--------|---------------------------|
| **Prompt quality** | 20 | Versioned GPT contract (`GPTAnalysisService::PROMPT_VERSION`), layered system prompt (domain + fidelity + multilingual + playbook + JSON schema), `response_format: json_object`, post-parse normalization in `SalesPlaybookService` / `GPTAnalysisService`. See **`docs/PROMPT_ENGINEERING.md`**. |
| **Problem understanding** | 15 | Kitchen-cabinet sales context, **Common Sales Question Library Q1–Q15** (`config/sales_questions.php`), playbook coverage UI + reports heatmap, multilingual transcription + English UI analysis. |
| **AI utilization strategy** | 15 | Whisper → transcript + language; GPT structured analytics; optional language hint; caching for dashboard/reports. Documented in **`docs/AI_STRATEGY.md`**. |
| **Code quality** | 15 | Strict PHP types, PDO prepared statements, thin controllers, services for AI/storage, centralized helpers, CSRF + rate limits. |
| **UI/UX design** | 10 | Dashboard, call detail hero + tabs, reports with charts, print-friendly analysis, responsive layout (Tailwind). |
| **Architecture & structure** | 10 | Router in `public/index.php`, `Controllers` / `Services` / `Models` / `Views`, pipeline in `CallPipelineService`. See **`docs/ARCHITECTURE.md`**. |
| **Real-world applicability** | 10 | Auth, `.env` config, migrations, upload limits doc, audio outside web root, logging, production notes in README. |
| **Demo & explanation** | 5 | **`docs/DEMO_SCRIPT.md`** — suggested 4-minute walkthrough for judges. |

**Key insight from rubric:** Prompt quality is weighted highest — keep **`docs/PROMPT_ENGINEERING.md`** and **`PROMPT_VERSION`** updated when prompts change.
