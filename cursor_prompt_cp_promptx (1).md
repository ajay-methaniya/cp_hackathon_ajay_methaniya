# 🚀 CP Prompt-X: AI Call Intelligence Platform — Cursor Master Prompt

> **Project:** Production-grade AI-powered Call Analysis & Intelligence Platform
> **Stack:** PHP 8.2+, MySQL 8, OpenAI API (Whisper + GPT-4o), Vanilla JS / Alpine.js, TailwindCSS
> **Build Tool:** Cursor IDE with AI assistance

---

## 🧠 PROJECT OVERVIEW

Build a full-stack **AI Call Intelligence Platform** that allows sales/support teams to:
1. Upload audio call recordings
2. Auto-transcribe them using OpenAI Whisper
3. Run GPT-4o AI analysis (sentiment, keywords, scoring, follow-ups)
4. View per-call intelligence dashboards
5. Track team/agent performance through an aggregated main dashboard

This is a **production application** — not a prototype. Write clean, maintainable, well-commented PHP with proper MVC separation, error handling, security, and scalability in mind.

---

## 📁 DIRECTORY ARCHITECTURE

```
/cp-promptx/
│
├── /public/                        # Web root (only this folder is publicly accessible)
│   ├── index.php                   # Entry point / router
│   ├── /css/
│   │   └── app.css                 # TailwindCSS compiled + custom styles
│   ├── /js/
│   │   ├── app.js                  # Global Alpine.js + utilities
│   │   ├── dashboard.js            # Main dashboard charts (Chart.js)
│   │   ├── call.js                 # Individual call page logic
│   │   └── upload.js               # Audio upload + progress handler
│   ├── /uploads/                   # Uploaded audio files (gitignored)
│   └── /assets/
│       └── /icons/                 # SVG icons
│
├── /app/
│   ├── /Controllers/
│   │   ├── DashboardController.php
│   │   ├── CallController.php
│   │   ├── UploadController.php
│   │   └── AuthController.php
│   │
│   ├── /Models/
│   │   ├── Call.php
│   │   ├── Transcript.php
│   │   ├── Analysis.php
│   │   ├── Agent.php
│   │   └── User.php
│   │
│   ├── /Services/
│   │   ├── WhisperService.php      # OpenAI Whisper transcription
│   │   ├── GPTAnalysisService.php  # GPT-4o call analysis
│   │   ├── SentimentService.php    # Sentiment scoring logic
│   │   ├── KeywordService.php      # Keyword extraction
│   │   └── FileStorageService.php  # Audio file handling
│   │
│   ├── /Views/
│   │   ├── /layouts/
│   │   │   ├── main.php            # Master layout with sidebar
│   │   │   └── auth.php            # Auth layout
│   │   ├── dashboard/
│   │   │   └── index.php           # Main dashboard view
│   │   ├── calls/
│   │   │   ├── index.php           # All calls list view
│   │   │   ├── show.php            # Individual call dashboard
│   │   │   ├── upload.php          # Upload new call
│   │   │   └── partials/
│   │   │       ├── _sentiment.php
│   │   │       ├── _keywords.php
│   │   │       ├── _questionnaire.php
│   │   │       └── _followups.php
│   │   └── auth/
│   │       ├── login.php
│   │       └── register.php
│   │
│   └── /Middleware/
│       ├── AuthMiddleware.php
│       └── RateLimitMiddleware.php
│
├── /config/
│   ├── app.php                     # App config (name, env, debug)
│   ├── database.php                # DB connection config
│   ├── openai.php                  # OpenAI API config
│   └── storage.php                 # File storage paths/limits
│
├── /database/
│   ├── schema.sql                  # Full DB schema
│   └── /migrations/
│       ├── 001_create_users.sql
│       ├── 002_create_calls.sql
│       ├── 003_create_transcripts.sql
│       ├── 004_create_analyses.sql
│       └── 005_create_agents.sql
│
├── /bootstrap/
│   └── app.php                     # Autoloader + DI container init
│
├── /storage/
│   ├── /logs/                      # App logs
│   └── /cache/                     # Analysis cache (JSON)
│
├── .env                            # Environment variables (gitignored)
├── .env.example
├── composer.json
├── tailwind.config.js
└── README.md
```

---

## 🗄️ DATABASE SCHEMA

```sql
-- Run in MySQL 8+

CREATE DATABASE IF NOT EXISTS cp_promptx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cp_promptx;

-- Users / Agents
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(200) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','agent','viewer') DEFAULT 'agent',
    avatar_url  VARCHAR(500) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Calls (audio uploads)
CREATE TABLE calls (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(300) NOT NULL,
    audio_file_path VARCHAR(500) NOT NULL,
    audio_duration  INT NULL COMMENT 'Duration in seconds',
    audio_format    VARCHAR(20) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    status          ENUM('uploaded','transcribing','analyzing','complete','failed') DEFAULT 'uploaded',
    contact_name    VARCHAR(200) NULL,
    contact_role    VARCHAR(200) NULL,
    contact_tenure  VARCHAR(100) NULL,
    call_date       DATE NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transcripts
CREATE TABLE transcripts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id     INT UNSIGNED NOT NULL UNIQUE,
    raw_text    LONGTEXT NOT NULL,
    segments    JSON NULL COMMENT 'Whisper timestamped segments array',
    language    VARCHAR(10) DEFAULT 'en',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
);

-- AI Analyses (one per call)
CREATE TABLE analyses (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id                 INT UNSIGNED NOT NULL UNIQUE,
    
    -- Sentiment & Performance Scoring
    overall_sentiment       ENUM('positive','neutral','negative') DEFAULT 'neutral',
    sentiment_score         DECIMAL(4,2) NULL COMMENT '-1.0 to +1.0',
    agent_confidence_score  DECIMAL(5,2) NULL COMMENT '0-100',
    agent_liveness_pct      DECIMAL(5,2) NULL COMMENT '% of call agent was engaged',
    previous_handling_score DECIMAL(5,2) NULL COMMENT '0-100',
    sentiment_evolution     JSON NULL COMMENT 'Array of {time, score} for chart',
    
    -- Summary & Insights
    call_summary            TEXT NULL,
    key_topics              JSON NULL COMMENT 'Array of topic strings',
    
    -- Business Questionnaire
    budget_discussed        TINYINT(1) DEFAULT 0,
    related_project         TINYINT(1) DEFAULT 0,
    business_strategy       TINYINT(1) DEFAULT 0,
    marketing_discussed     TINYINT(1) DEFAULT 0,
    
    -- Keywords
    keywords_discussed      JSON NULL COMMENT 'Array of {word, count, category}',
    
    -- Follow-Up Actions
    follow_up_actions       JSON NULL COMMENT 'Array of {action, priority, owner}',
    
    -- Observations
    positive_observations   JSON NULL COMMENT 'Array of strings',
    negative_observations   JSON NULL COMMENT 'Array of strings',
    
    -- AI Meta
    gpt_model_used          VARCHAR(50) DEFAULT 'gpt-4o',
    tokens_used             INT UNSIGNED NULL,
    analysis_duration_ms    INT UNSIGNED NULL,
    
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
);

-- Call Notes (manual notes per call)
CREATE TABLE call_notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    note        TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_calls_user ON calls(user_id);
CREATE INDEX idx_calls_status ON calls(status);
CREATE INDEX idx_calls_date ON calls(call_date);
CREATE INDEX idx_analyses_sentiment ON analyses(overall_sentiment);
```

---

## ⚙️ ENVIRONMENT CONFIGURATION

Create `.env` file:
```env
APP_NAME="CP Prompt-X"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cp_promptx
DB_USERNAME=root
DB_PASSWORD=your_password

OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_WHISPER_MODEL=whisper-1
OPENAI_GPT_MODEL=gpt-4o

MAX_UPLOAD_SIZE_MB=100
ALLOWED_AUDIO_FORMATS=mp3,mp4,wav,m4a,ogg,webm

SESSION_LIFETIME=120
```

---

## 🔧 COMPOSER DEPENDENCIES

`composer.json`:
```json
{
    "require": {
        "php": ">=8.2",
        "vlucas/phpdotenv": "^5.6",
        "openai-php/client": "^0.10",
        "league/flysystem": "^3.0",
        "monolog/monolog": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

---

## 📡 APPLICATION WORKFLOW (5-Step Pipeline)

Implement the following pipeline exactly:

```
[1] UPLOAD AUDIO
    → User uploads MP3/WAV/M4A via drag-drop UI
    → PHP validates file type, size, saves to /public/uploads/{uuid}.{ext}
    → Creates `calls` record with status='uploaded'
    → Returns call_id to frontend
         ↓
[2] TRANSCRIBE
    → WhisperService.php calls OpenAI Whisper API
    → Sends audio file as multipart/form-data
    → Receives transcript text + timestamped segments
    → Saves to `transcripts` table
    → Updates call status='transcribing' → 'analyzing'
         ↓
[3] AI ANALYSIS
    → GPTAnalysisService.php calls GPT-4o with structured prompt
    → Prompt instructs GPT to return ONLY valid JSON with all required fields
    → Fields: summary, sentiment, confidence_score, liveness_pct,
              keywords, follow_up_actions, positive_obs, negative_obs,
              budget_discussed, project_related, business_strategy,
              sentiment_evolution (array), key_topics
    → Saves to `analyses` table
    → Updates call status='complete'
         ↓
[4] GENERATE INSIGHTS
    → Aggregation queries run across all completed analyses
    → Top keywords, avg sentiment, agent scores calculated
    → Cached in /storage/cache/ as JSON (15-min TTL)
         ↓
[5] DASHBOARD
    → Main dashboard reads aggregated insights
    → Individual call page reads full analysis
    → Real-time status polling via JS fetch (every 3s) until status='complete'
```

---

## 🎨 UI PAGES & FEATURES TO BUILD

### PAGE 1: Main Dashboard (`/dashboard`)

**Left Panel — Filters / Pull Factors:**
- Filter by: Date Range, Agent, Sentiment (Positive/Neutral/Negative), Duration
- "Manage Pull Filters" button — opens modal with saved filter sets
- Active filter chips showing applied filters with ✕ to remove

**Center/Right Panel — Stats & Insights:**
- KPI Cards row:
  - Total Calls (with trend arrow vs last period)
  - Avg Sentiment Score (color-coded)
  - Avg Agent Confidence %
  - Total Follow-Up Actions pending
- **Top Keywords Chart** — horizontal bar chart (Chart.js) showing top 10 keywords
- **Sentiment Distribution** — donut chart (positive/neutral/negative)
- **Calls Timeline** — line chart showing calls per day over 30 days
- **Recent Calls Table** — last 10 calls with: Title, Agent, Date, Duration, Sentiment badge, Status, Actions (View / Delete)
- **"Play Pull Factors"** button — highlights top performing calls

---

### PAGE 2: Upload New Call (`/calls/upload`)

- Drag-and-drop audio uploader with:
  - Accepted formats clearly shown (MP3, WAV, M4A, OGG, MP4, WEBM)
  - Max file size indicator
  - Progress bar during upload
  - Live status updates: "Uploading... → Transcribing... → Analyzing... → Complete ✓"
  - Auto-redirect to call dashboard on completion
- Form fields alongside upload:
  - Call Title (required)
  - Contact Name
  - Contact Role (e.g., CEO, Manager, etc.)
  - Contact Tenure
  - Call Date (date picker)
  - Assign to Agent (dropdown from users table)

---

### PAGE 3: Individual Call Dashboard (`/calls/{id}`)

This page has 4 distinct sections/tabs or scrollable panels:

#### Panel A — Call Summary & AI Analysis
- **Audio Player** — custom HTML5 audio player with:
  - Waveform visualization (static or animated)
  - Play/Pause, scrub bar, volume, speed controls
  - Current time / total duration display
- **Call Summary** — AI-generated paragraph summary of the call
- **Key Topics** — pill/badge list of identified topics
- **Right sidebar stats:**
  - Duration, Date, Agent, Contact
  - Status badge (Complete / Processing / Failed)
  - "Create Call Note" button → opens inline textarea → saves to call_notes
  - Existing notes listed below with timestamp

#### Panel B — Agent Sentiment & Performance Scoring
- **3 Score Cards:**
  1. Previous Handling Score (0–100 with colored progress ring)
  2. Agent Liveness % (how much of call agent was active/engaged)
  3. Confidence Score (0–100 with progress ring)
- **Sentiment Evolution Chart** (Chart.js line chart):
  - X-axis: time through call (in minutes)
  - Y-axis: sentiment score (-1 to +1)
  - Smooth gradient fill below line
  - Color transitions: red (negative) → yellow (neutral) → green (positive)
  - Tooltip on hover showing exact score + transcript excerpt at that timestamp

#### Panel C — Business Questionnaire & Keyword Analysis
- **Left: Business Questionnaire**
  - Contact Role field (read from call record)
  - Contact Tenure field
  - Checkbox grid (read-only, AI-detected):
    - ☑ Budget
    - ☑ Related Project
    - ☐ Business Strategy
    - ☑ Marketing
  - Visual indication of AI confidence per checkbox
- **Right: Keywords Discussed**
  - Tag cloud OR ranked list of top keywords
  - Each keyword shows: word, frequency count, category badge (Technical / Financial / People / Process)
  - Color-coded by category
  - Clicking a keyword highlights occurrences in transcript view

#### Panel D — Follow-Up Actions & AI-Generated Notes
- **Follow-Up Action Items** (left column):
  - Checklist of AI-generated action items
  - Each item: checkbox (mark as done), action text, priority badge (High/Medium/Low)
  - "Add Manual Action" button
  - Completed items shown with strikethrough
- **Observations** (right column):
  - **Positive Observations** section (green-accented):
    - Bullet list of AI-identified positive points
  - **Negative Observations** section (red-accented):
    - Bullet list of AI-identified areas for improvement

---

## 🤖 GPT-4o ANALYSIS PROMPT

In `GPTAnalysisService.php`, send this system prompt to GPT-4o:

```php
$systemPrompt = <<<PROMPT
You are an expert sales call analyst AI. Analyze the provided call transcript and return ONLY a valid JSON object — no markdown, no explanation, no preamble.

Return this exact JSON structure:
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
  "key_topics": ["pricing", "implementation timeline", "support"],
  "budget_discussed": true,
  "related_project": true,
  "business_strategy": false,
  "marketing_discussed": false,
  "keywords_discussed": [
    {"word": "budget", "count": 4, "category": "Financial"},
    {"word": "integration", "count": 3, "category": "Technical"}
  ],
  "follow_up_actions": [
    {"action": "Send pricing proposal by Friday", "priority": "High", "owner": "Agent"},
    {"action": "Schedule technical demo", "priority": "Medium", "owner": "Agent"}
  ],
  "positive_observations": [
    "Agent clearly explained product benefits",
    "Strong rapport established early in the call"
  ],
  "negative_observations": [
    "Pricing objection was not fully addressed",
    "Call ended without clear next steps from prospect"
  ]
}

Scoring guidelines:
- sentiment_score: -1.0 (very negative) to +1.0 (very positive)
- agent_confidence_score: 0-100 (based on clarity, pace, assertiveness, handling objections)
- agent_liveness_pct: 0-100 (% of call where agent was actively engaged, not just listening)
- previous_handling_score: 0-100 (how well agent handled prior context/objections from previous interactions)
- sentiment_evolution: sample every ~60 seconds of call, minimum 5 data points
- keywords: top 10-15 most significant business keywords only
PROMPT;
```

---

## 🔄 ASYNC PROCESSING (Status Polling)

Since Whisper + GPT analysis can take 30–90 seconds, implement a **polling mechanism**:

```javascript
// In upload.js
async function pollCallStatus(callId) {
    const statusEl = document.getElementById('status-message');
    const statuses = {
        'uploaded': 'File received. Starting transcription...',
        'transcribing': '🎙️ Transcribing audio with Whisper AI...',
        'analyzing': '🤖 Running GPT-4o analysis...',
        'complete': '✅ Analysis complete! Redirecting...',
        'failed': '❌ Processing failed. Please try again.'
    };

    const interval = setInterval(async () => {
        const res = await fetch(`/api/calls/${callId}/status`);
        const data = await res.json();
        statusEl.textContent = statuses[data.status] || 'Processing...';
        
        if (data.status === 'complete') {
            clearInterval(interval);
            window.location.href = `/calls/${callId}`;
        }
        if (data.status === 'failed') {
            clearInterval(interval);
        }
    }, 3000); // Poll every 3 seconds
}
```

Create a lightweight status endpoint: `GET /api/calls/{id}/status` → returns `{"status": "analyzing"}`

---

## 🔐 AUTHENTICATION & SECURITY

### Auth System:
- **Session-based auth** (PHP sessions)
- Login / Register pages
- Password hashing: `password_hash()` with `PASSWORD_BCRYPT`
- Auth middleware on all protected routes
- CSRF token on all forms

### Security Hardening (implement all):
```php
// In every controller that handles uploads:
// 1. Validate file MIME type (not just extension)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['audio']['tmp_name']);
$allowed = ['audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/ogg', 'audio/webm'];
if (!in_array($mime, $allowed)) {
    throw new \Exception('Invalid file type');
}

// 2. Rename file to UUID to prevent path traversal
$filename = bin2hex(random_bytes(16)) . '.' . $extension;

// 3. Store outside web root if possible, serve via controller

// 4. Prepared statements EVERYWHERE — no raw SQL string concatenation
// 5. htmlspecialchars() on all output
// 6. Rate limiting on upload endpoint (max 10 uploads/hour per user)
```

---

## 📊 API ENDPOINTS (PHP Routes)

```
GET    /                          → Redirect to /dashboard
GET    /dashboard                 → DashboardController::index()
GET    /calls                     → CallController::index()
GET    /calls/upload              → CallController::uploadForm()
POST   /calls/upload              → CallController::store()
GET    /calls/{id}                → CallController::show()
DELETE /calls/{id}                → CallController::destroy()

GET    /api/calls/{id}/status     → CallController::status() [JSON]
GET    /api/dashboard/stats       → DashboardController::stats() [JSON]
GET    /api/dashboard/keywords    → DashboardController::keywords() [JSON]

POST   /calls/{id}/notes          → CallController::addNote()
PATCH  /calls/{id}/followups/{fid} → CallController::toggleFollowUp()

GET    /auth/login                → AuthController::loginForm()
POST   /auth/login                → AuthController::login()
POST   /auth/logout               → AuthController::logout()
GET    /auth/register             → AuthController::registerForm()
POST   /auth/register             → AuthController::register()
```

---

## 🎨 UI DESIGN SYSTEM

Use TailwindCSS with this custom color palette (add to `tailwind.config.js`):

```javascript
module.exports = {
    theme: {
        extend: {
            colors: {
                brand: {
                    50:  '#f0f4ff',
                    100: '#e0e9ff',
                    500: '#4f6ef7',
                    600: '#3a56e8',
                    700: '#2d44d4',
                    900: '#1a2a8a',
                },
                surface: {
                    DEFAULT: '#0f1117',
                    card:    '#1a1d27',
                    border:  '#2a2d3e',
                    hover:   '#22253a',
                },
                positive: '#22c55e',
                negative: '#ef4444',
                neutral:  '#f59e0b',
            },
            fontFamily: {
                sans:    ['DM Sans', 'sans-serif'],
                display: ['Syne', 'sans-serif'],
                mono:    ['JetBrains Mono', 'monospace'],
            }
        }
    }
}
```

**Import Google Fonts in layout:**
```html
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Syne:wght@600;700;800&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
```

**Design principles for this app:**
- Dark theme (`#0f1117` base) — enterprise intelligence aesthetic
- Glowing accent on active cards (subtle `box-shadow: 0 0 20px rgba(79,110,247,0.15)`)
- Sentiment colors: Green (#22c55e) / Amber (#f59e0b) / Red (#ef4444)
- Score rings: SVG `stroke-dasharray` animated progress circles
- Charts: Chart.js with dark theme, no grid lines, smooth curves
- Status badges: pill-shaped with dot indicator

---

## 📋 IMPLEMENTATION CHECKLIST FOR CURSOR

Build in this exact order:

- [ ] **Step 1:** Set up directory structure, `.env`, `composer.json`, autoloader in `bootstrap/app.php`
- [ ] **Step 2:** Create `config/database.php` with PDO connection class
- [ ] **Step 3:** Run `database/schema.sql` — all tables created
- [ ] **Step 4:** Build router in `public/index.php` — parse URL, dispatch to controllers
- [ ] **Step 5:** Build `AuthController` — login, register, logout with sessions
- [ ] **Step 6:** Build `AuthMiddleware` — redirect unauthenticated users
- [ ] **Step 7:** Build master layout `views/layouts/main.php` — sidebar, topbar, content slot
- [ ] **Step 8:** Build `UploadController` + upload view — drag-drop, file validation, save call record
- [ ] **Step 9:** Build `WhisperService.php` — multipart file upload to OpenAI Whisper API
- [ ] **Step 10:** Build `GPTAnalysisService.php` — send transcript, parse JSON response, save analysis
- [ ] **Step 11:** Build status polling endpoint + frontend JS polling loop
- [ ] **Step 12:** Build `CallController::show()` + individual call view with all 4 panels
- [ ] **Step 13:** Build sentiment evolution Chart.js line chart with gradient fill
- [ ] **Step 14:** Build follow-up action toggle endpoint (AJAX PATCH)
- [ ] **Step 15:** Build `DashboardController` + main dashboard with KPI cards + all charts
- [ ] **Step 16:** Build filter system on dashboard (date, agent, sentiment)
- [ ] **Step 17:** Add call notes (create/list per call)
- [ ] **Step 18:** Add delete call functionality (cascade delete audio + db records)
- [ ] **Step 19:** Add error handling, logging (Monolog), user-facing error pages
- [ ] **Step 20:** Write `README.md` with setup instructions

---

## 🧪 WHISPER SERVICE IMPLEMENTATION

```php
<?php
// app/Services/WhisperService.php
namespace App\Services;

class WhisperService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENAI_API_KEY'];
        $this->model  = $_ENV['OPENAI_WHISPER_MODEL'] ?? 'whisper-1';
    }

    public function transcribe(string $audioFilePath): array
    {
        $curl = curl_init();
        
        $cfile = new \CURLFile(
            $audioFilePath,
            mime_content_type($audioFilePath),
            basename($audioFilePath)
        );

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => [
                'file'             => $cfile,
                'model'            => $this->model,
                'response_format'  => 'verbose_json', // Gets segments with timestamps
                'timestamp_granularities[]' => 'segment',
            ],
            CURLOPT_TIMEOUT        => 300, // 5 minutes for long audio
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            throw new \Exception('Whisper API error: ' . $response);
        }

        $data = json_decode($response, true);
        
        return [
            'text'     => $data['text'],
            'segments' => $data['segments'] ?? [],
            'language' => $data['language'] ?? 'en',
            'duration' => $data['duration'] ?? null,
        ];
    }
}
```

---

## 📌 CURSOR RULES FILE (`.cursorrules`)

Create this file in the project root:

```
You are building a production PHP 8.2 application called CP Prompt-X — an AI Call Intelligence Platform.

ALWAYS:
- Use PDO with prepared statements for all database queries
- Sanitize output with htmlspecialchars() before rendering
- Validate MIME types for file uploads (not just extensions)
- Add meaningful PHP docblocks to all methods
- Use typed properties and return types in PHP classes
- Handle API errors gracefully with try/catch and user-friendly messages
- Keep controllers thin — business logic belongs in Services
- Use Alpine.js x-data directives for interactive UI components
- Use Chart.js for all data visualizations

NEVER:
- Use raw SQL string concatenation
- Store sensitive data in localStorage
- Trust $_FILES['type'] for MIME validation — always use finfo
- Write JavaScript that blocks the main thread during API calls
- Skip error handling on OpenAI API calls (they can fail/timeout)
- Use inline styles — use Tailwind utility classes instead

FILE STRUCTURE CONVENTION:
- Controllers: handle HTTP request/response only
- Services: contain business logic and external API calls
- Models: contain database CRUD operations
- Views: contain only HTML/PHP templating, no logic
```

---

## 📦 README TEMPLATE

```markdown
# CP Prompt-X — AI Call Intelligence Platform

## Requirements
- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js 18+ (for Tailwind build)
- OpenAI API Key (with access to Whisper + GPT-4o)

## Setup

1. Clone the repository
   git clone https://github.com/your-org/cp-promptx.git
   cd cp-promptx

2. Install PHP dependencies
   composer install

3. Install Node dependencies & build CSS
   npm install
   npm run build

4. Configure environment
   cp .env.example .env
   # Edit .env with your DB credentials and OpenAI API key

5. Create database & run migrations
   mysql -u root -p < database/schema.sql

6. Set permissions
   chmod -R 775 public/uploads storage/

7. Configure web server (Apache/Nginx) to point to /public

8. Visit http://localhost and register your first account

## Tech Stack
- Backend: PHP 8.2, PDO/MySQL
- AI: OpenAI Whisper (transcription) + GPT-4o (analysis)
- Frontend: TailwindCSS, Alpine.js, Chart.js
- Fonts: DM Sans, Syne, JetBrains Mono
```

---

## 🏆 EVALUATION CRITERIA ALIGNMENT

| Criterion | How This Architecture Addresses It |
|-----------|-----------------------------------|
| **Prompt Quality** | Detailed GPT-4o system prompt with structured JSON schema and scoring guidelines |
| **AI/ML Strategy** | Two-stage AI pipeline: Whisper (STT) → GPT-4o (NLU analysis) |
| **Full Stack Design** | PHP MVC backend + TailwindCSS + Alpine.js + Chart.js frontend |
| **User Friendly/Scoring** | Visual score rings, color-coded sentiment, drag-drop upload, real-time status |
| **Business Solution** | Actionable follow-ups, keyword analysis, performance scoring for sales teams |
| **Audio Quality/Benchmarks** | Whisper verbose_json with segment timestamps for temporal analysis |
| **Architecture & Structure** | Clean MVC, service layer, migrations, env config, PSR-4 autoloading |
| **Demo & Presentation** | Status polling creates smooth demo flow; all data visible in one dashboard |

---

*Built for CP Prompt-X: The AI Vibe Coding Hackathon*
*Stack: PHP 8.2 · MySQL 8 · OpenAI Whisper · GPT-4o · TailwindCSS · Alpine.js · Chart.js*
```
