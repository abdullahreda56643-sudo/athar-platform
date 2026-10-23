-- Run this migration against an existing teacher_experiences database.
-- Back up the database before applying schema changes.

ALTER TABLE experiences
    ADD COLUMN rejection_reason VARCHAR(1000) NULL AFTER status;

CREATE TABLE admin_activity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id BIGINT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    details VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY admin_activity_created_index (created_at),
    CONSTRAINT admin_activity_admin_fk FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
