ALTER TABLE analyses
    ADD COLUMN sales_question_coverage JSON NULL
        COMMENT 'Per Q1–Q15 playbook coverage from GPT analysis'
    AFTER negative_observations;
