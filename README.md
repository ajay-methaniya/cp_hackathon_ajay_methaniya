# CP Prompt-X — AI Call Intelligence Platform

Production-oriented PHP application for uploading sales/support calls, transcribing with OpenAI Whisper, analyzing with a configurable GPT model (default **gpt-4o-mini**), and visualizing team performance.

## Hackathon / judging (evaluation criteria)

Official rubric areas (prompt quality, problem fit, AI strategy, code, UI/UX, architecture, real-world use, demo) are mapped to this repo in **`docs/HACKATHON_EVALUATION.md`**.

| Doc | Purpose |
|-----|---------|
| **`docs/HACKATHON_EVALUATION.md`** | Criterion → evidence checklist |
| **`docs/PROMPT_ENGINEERING.md`** | GPT prompt layers, versioning (`GPTAnalysisService::PROMPT_VERSION`) |
| **`docs/AI_STRATEGY.md`** | Whisper + GPT pipeline and design choices |
| **`docs/ARCHITECTURE.md`** | Structure diagram (Mermaid) |
| **`docs/DEMO_SCRIPT.md`** | Suggested live demo flow |

## Requirements

- PHP 8.2+ with extensions: `pdo_mysql`, `curl`, `fileinfo`, `json`, `mbstring`
- MySQL 8.0+
- Composer
- Node.js 18+ (Tailwind build)
- OpenAI API key with access to **Whisper** (`whisper-1`) and a chat model you set in `.env` (default **gpt-4o-mini**)

## Setup

1. **Enter the project**

   ```bash
   cd cp-promptx
   ```

2. **Install dependencies**

   ```bash
   composer install
   npm install
   npm run build
   ```

   `composer.json` includes **`php-http/guzzle7-adapter`** so the OpenAI PHP client has a PSR-18 HTTP client (required for GPT calls).

3. **Environment**

   ```bash
   cp .env.example .env
   ```

   Edit `.env`: set `DB_*`, `OPENAI_API_KEY`, and `APP_URL`.

4. **Database** (required — without this, registration fails)

   ```bash
   mysql -u root -p < database/schema.sql
   ```

   Or **phpMyAdmin**: create database `cp_promptx` (utf8mb4_unicode_ci), then **Import** `database/schema.sql`. Step-by-step: **`database/DATABASE.md`**.

   Set **`DB_USERNAME`** and **`DB_PASSWORD`** in `.env` to match your MySQL user (same as phpMyAdmin).

5. **Permissions**

   ```bash
   chmod -R 775 storage public/uploads
   ```

6. **Web server**

   Point the document root to the `public/` directory (Apache `AllowOverride All` for `.htaccess`, or equivalent Nginx rewrite to `index.php`).

   PHP built-in server (development only):

   ```bash
   php -S localhost:8080 -t public
   ```

7. **First user**

   Open `/auth/register`, create an account, then sign in. Audio files are stored under `storage/audio/` (outside the public root) and streamed via `/calls/{id}/audio` after authentication.

### PHP upload limits (fixes “No audio file” for ~8 MB files)

PHP defaults are often **`upload_max_filesize=2M`** and **`post_max_size=8M`**. The **entire POST** (file + form fields + multipart overhead) must be **under `post_max_size`**, so an **8.4 MB file** can fail when the limit is **8M**.

1. The repo includes **`public/.user.ini`** (100M / 105M). If your server reads per-directory `.user.ini`, reload Apache and retry (wait up to ~5 minutes for cache, or restart Apache).
2. If uploads still fail, edit the **Apache** PHP ini (not CLI), e.g. on Ubuntu:

   ```bash
   sudo nano /etc/php/8.3/apache2/php.ini   # adjust version if needed
   ```

   Set `upload_max_filesize = 100M` and `post_max_size = 105M`, then:

   ```bash
   sudo systemctl reload apache2
   ```

### Apache: project under `html/hackathon/…` (not at domain root)

If you open `http://localhost/hackathon/cp-promptx/` and see a **directory listing**, Apache is pointing at the **repository folder**, not the app. Do this:

1. Use the real entry point: **`http://localhost/hackathon/cp-promptx/public/`** (or let the new root `index.php` redirect you from `…/cp-promptx/` → `…/cp-promptx/public/` when `DirectoryIndex` runs `index.php`).
2. Set **`APP_BASE_PATH`** in `.env` to the URL path of the `public` folder **without a trailing slash**, e.g.  
   `APP_BASE_PATH=/hackathon/cp-promptx/public`  
   so links, CSS, JS, and API calls resolve correctly.
3. **`.htaccess` must be read by Apache.** On Ubuntu the default is **`AllowOverride None`** for `/var/www/`, which **ignores all `.htaccess` files** — you will get Apache’s own 404 for `/public/dashboard` (not the app’s 404). Fix one of:
   - **Recommended:** install the snippet in `deploy/apache-allow-htaccess.conf` (see comments in that file), then `sudo systemctl reload apache2`.
   - Or set **`AllowOverride All`** for `/var/www/` (or your project’s `public/` folder) in your Apache config.
4. Ensure **`mod_rewrite`** is enabled: `sudo a2enmod rewrite` then reload Apache.

If the app is the **only** vhost and `DocumentRoot` is `cp-promptx/public`, leave **`APP_BASE_PATH` empty**.

## Processing pipeline

1. Upload saves the file, creates a `calls` row (`status=uploaded`), returns JSON to the browser.
2. A `register_shutdown_function` run (after the response when using PHP-FPM + `fastcgi_finish_request`) starts **Whisper** transcription (multilingual; optional **language hint** on upload from `config/transcription_languages.php`), then **GPT** analysis (`OPENAI_GPT_MODEL`, default `gpt-4o-mini`). Analysis includes the hackathon **Common Sales Question Library** (kitchen cabinet sales **Q1–Q15** in `config/sales_questions.php`), per-question coverage, and dashboard **Playbook coverage** KPIs. GPT outputs English UI fields even when the transcript is non-English.

**Reports** (`/reports`): agent leaderboard, language distribution, playbook heatmap (Q1–Q15 averages), weekly sentiment, monthly sentiment volume — same filters as the dashboard (`GET /api/reports/overview`).
3. The UI polls `GET /api/calls/{id}/status` every 3 seconds until `complete` or `failed`.

If you use `php -S`, the request may stay open until processing finishes (no `fastcgi_finish_request`); polling still works once the response is sent.

## Tech stack

- Backend: PHP 8.2, PDO, MySQL
- AI: OpenAI Whisper + GPT (`openai-php/client` for chat, cURL for Whisper)
- Frontend: TailwindCSS, Alpine.js, Chart.js
- Fonts: DM Sans, Syne, JetBrains Mono

## API highlights

| Method | Path | Description |
|--------|------|----------------|
| GET | `/api/calls/{id}/status` | JSON `{ "status": "..." }` |
| GET | `/dashboard/export?date_from=&date_to=…` | CSV export of calls matching dashboard filters (max 1000 rows, UTF-8 BOM) |
| GET | `/api/dashboard/stats` | KPIs, sentiment split, timeline (cached ~15 min) |
| GET | `/api/dashboard/keywords` | Aggregated top keywords |
| GET | `/api/reports/overview` | Reports: agents, languages, playbook heatmap, trends (cached) |
| DELETE | `/calls/{id}` | JSON body or `X-CSRF-Token` for CSRF |
| PATCH | `/calls/{id}/followups/{index}` | Toggle follow-up completion |

## Security notes

- **Never paste API keys into shared docs, tickets, or chat** — if a key is exposed, rotate it in the OpenAI dashboard and update `.env` only on the server.
- Session-based auth; `password_hash` / `password_verify`
- CSRF tokens on forms; APIs accept `X-CSRF-Token` or JSON ` _csrf`
- Upload MIME validation via `finfo` (not `$_FILES['type']`)
- UUID-like filenames; audio outside `public/` by default
- Rate limit: 10 upload attempts per user per hour (file-based, see `RateLimitMiddleware`)

---

Built for CP Prompt-X / hackathon use. Adjust `.env` and logging under `storage/logs/` for production hardening.
