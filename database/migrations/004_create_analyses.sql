CREATE TABLE IF NOT EXISTS analyses (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id                 INT UNSIGNED NOT NULL UNIQUE,
    overall_sentiment       ENUM('positive','neutral','negative') DEFAULT 'neutral',
    sentiment_score         DECIMAL(4,2) NULL,
    agent_confidence_score  DECIMAL(5,2) NULL,
    agent_liveness_pct      DECIMAL(5,2) NULL,
    previous_handling_score DECIMAL(5,2) NULL,
    sentiment_evolution     JSON NULL,
    call_summary            TEXT NULL,
    key_topics              JSON NULL,
    budget_discussed        TINYINT(1) DEFAULT 0,
    related_project         TINYINT(1) DEFAULT 0,
    business_strategy       TINYINT(1) DEFAULT 0,
    marketing_discussed     TINYINT(1) DEFAULT 0,
    keywords_discussed      JSON NULL,
    follow_up_actions       JSON NULL,
    positive_observations   JSON NULL,
    negative_observations   JSON NULL,
    gpt_model_used          VARCHAR(50) DEFAULT 'gpt-4o-mini',
    tokens_used             INT UNSIGNED NULL,
    analysis_duration_ms    INT UNSIGNED NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
);

CREATE INDEX idx_analyses_sentiment ON analyses(overall_sentiment);
