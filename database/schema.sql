-- Run in MySQL 8+
CREATE DATABASE IF NOT EXISTS cp_promptx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cp_promptx;

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

CREATE TABLE calls (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(300) NOT NULL,
    audio_file_path VARCHAR(500) NOT NULL,
    audio_duration  INT NULL COMMENT 'Duration in seconds',
    audio_format    VARCHAR(20) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    status          ENUM('uploaded','transcribing','analyzing','complete','failed') DEFAULT 'uploaded',
    last_error      TEXT NULL COMMENT 'Last processing failure (Whisper/GPT) for debugging',
    contact_name    VARCHAR(200) NULL,
    contact_role    VARCHAR(200) NULL,
    contact_tenure  VARCHAR(100) NULL,
    call_date       DATE NULL,
    whisper_language_hint VARCHAR(12) NULL COMMENT 'Optional ISO 639-1 for Whisper; NULL = auto-detect',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE transcripts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id     INT UNSIGNED NOT NULL UNIQUE,
    raw_text    LONGTEXT NOT NULL,
    segments    JSON NULL COMMENT 'Whisper timestamped segments array',
    language    VARCHAR(10) DEFAULT 'en',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
);

CREATE TABLE analyses (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id                 INT UNSIGNED NOT NULL UNIQUE,
    overall_sentiment       ENUM('positive','neutral','negative') DEFAULT 'neutral',
    sentiment_score         DECIMAL(4,2) NULL COMMENT '-1.0 to +1.0',
    agent_confidence_score  DECIMAL(5,2) NULL COMMENT '0-100',
    agent_liveness_pct      DECIMAL(5,2) NULL COMMENT '% of call agent was engaged',
    previous_handling_score DECIMAL(5,2) NULL COMMENT '0-100',
    sentiment_evolution     JSON NULL COMMENT 'Array of {time, score} for chart',
    call_summary            TEXT NULL,
    key_topics              JSON NULL COMMENT 'Array of topic strings',
    budget_discussed        TINYINT(1) DEFAULT 0,
    related_project         TINYINT(1) DEFAULT 0,
    business_strategy       TINYINT(1) DEFAULT 0,
    marketing_discussed     TINYINT(1) DEFAULT 0,
    keywords_discussed      JSON NULL COMMENT 'Array of {word, count, category}',
    follow_up_actions       JSON NULL COMMENT 'Array of {action, priority, owner}',
    positive_observations   JSON NULL COMMENT 'Array of strings',
    negative_observations   JSON NULL COMMENT 'Array of strings',
    sales_question_coverage JSON NULL COMMENT 'Per Q1–Q15 playbook coverage from GPT',
    gpt_model_used          VARCHAR(50) DEFAULT 'gpt-4o-mini',
    tokens_used             INT UNSIGNED NULL,
    analysis_duration_ms    INT UNSIGNED NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
);

CREATE TABLE call_notes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    note        TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_calls_user ON calls(user_id);
CREATE INDEX idx_calls_status ON calls(status);
CREATE INDEX idx_calls_date ON calls(call_date);
CREATE INDEX idx_analyses_sentiment ON analyses(overall_sentiment);
