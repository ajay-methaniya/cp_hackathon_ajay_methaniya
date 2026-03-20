CREATE TABLE IF NOT EXISTS calls (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(300) NOT NULL,
    audio_file_path VARCHAR(500) NOT NULL,
    audio_duration  INT NULL,
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

CREATE INDEX idx_calls_user ON calls(user_id);
CREATE INDEX idx_calls_status ON calls(status);
CREATE INDEX idx_calls_date ON calls(call_date);
