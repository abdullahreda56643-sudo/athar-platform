-- Run this migration against an existing teacher_experiences database.
-- Back up the database before applying schema changes.

CREATE TABLE experience_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_key VARCHAR(60) NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(16) NOT NULL DEFAULT '✧',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY experience_categories_key_unique (category_key),
    UNIQUE KEY experience_categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO experience_categories (category_key, name, icon, sort_order) VALUES
    ('teaching', 'استراتيجيات التدريس', '◉', 1),
    ('classroom', 'إدارة الصف', '▤', 2),
    ('technology', 'التقنية في التعليم', '⌘', 3),
    ('educational', 'مواقف تربوية', '◇', 4),
    ('other', 'أخرى', '✧', 5);

ALTER TABLE experiences
    MODIFY COLUMN category VARCHAR(60) NOT NULL DEFAULT 'other',
    ADD KEY experiences_category_index (category),
    ADD CONSTRAINT experiences_category_fk
        FOREIGN KEY (category) REFERENCES experience_categories (category_key) ON DELETE RESTRICT;
