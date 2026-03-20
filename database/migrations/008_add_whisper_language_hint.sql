ALTER TABLE calls
    ADD COLUMN whisper_language_hint VARCHAR(12) NULL
        COMMENT 'Optional ISO 639-1 hint for Whisper; NULL = auto-detect'
    AFTER call_date;
