-- LMS subsystem: 5-level AI curriculum with topics, structured content blocks,
-- quiz banks (review / pre / post) and the skill-mission promotion workflow.
-- Idempotent: every statement is CREATE TABLE IF NOT EXISTS, so re-running is safe.

-- Levels: the 5-rung ladder. sort_order doubles as the ladder position
-- (the "previous level" is simply the row before this one).
CREATE TABLE IF NOT EXISTS lms_levels (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(64)  NOT NULL UNIQUE,          -- stable key used by the PHP seeder
    title               VARCHAR(150) NOT NULL,
    subtitle            VARCHAR(255) NULL,
    description         TEXT NULL,
    icon                VARCHAR(40)  NOT NULL DEFAULT 'bi-mortarboard',
    accent_color        CHAR(7)      NOT NULL DEFAULT '#2563EB',
    pass_percent        TINYINT UNSIGNED NOT NULL DEFAULT 80,  -- post-test % needed to unlock the next level
    pre_question_count  TINYINT UNSIGNED NOT NULL DEFAULT 10,  -- how many to draw from the pre bank per attempt
    post_question_count TINYINT UNSIGNED NOT NULL DEFAULT 10,
    review_pass_correct TINYINT UNSIGNED NOT NULL DEFAULT 2,   -- correct answers out of 3 needed to clear a topic
    promo_group_id      INT UNSIGNED NULL,                     -- user_groups row granted on mission approval
    mission_brief       TEXT NULL,                             -- the admin-editable skill mission
    is_published        TINYINT(1) NOT NULL DEFAULT 0,         -- note: default 0, unlike the house is_* default of 1
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_levels_group FOREIGN KEY (promo_group_id) REFERENCES user_groups(id) ON DELETE SET NULL,
    KEY idx_lms_levels_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics (หัวข้อย่อย) within a level.
CREATE TABLE IF NOT EXISTS lms_topics (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id     INT UNSIGNED NOT NULL,
    slug         VARCHAR(64)  NOT NULL,                        -- unique within the level, used by the seeder
    title        VARCHAR(200) NOT NULL,
    summary      VARCHAR(500) NULL,                            -- one-line teaser on the level page
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_topics_level FOREIGN KEY (level_id) REFERENCES lms_levels(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_lms_topics_slug (level_id, slug),
    KEY idx_lms_topics_level (level_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structured content blocks. One flat table: nullable columns carry the type-specific
-- payload and LmsContent::saveBlock() validates which are required per block_type.
-- Nothing here is ever rendered as raw HTML — every field is escaped on output.
CREATE TABLE IF NOT EXISTS lms_blocks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id     INT UNSIGNED NOT NULL,
    block_type   ENUM('heading','paragraph','list','image','code','youtube','callout') NOT NULL DEFAULT 'paragraph',
    text_content TEXT NULL,          -- heading/paragraph/code/callout body. For 'list', one item per line
    image_url    VARCHAR(500) NULL,  -- hotlinked image, https only. Mutually exclusive with image_file
    image_file   VARCHAR(255) NULL,  -- bare filename under uploads/lms/blocks/ when the admin uploads instead
    link_url     VARCHAR(500) NULL,  -- youtube watch URL, or the callout/link target
    source_url   VARCHAR(500) NULL,  -- REQUIRED on image blocks: where the image came from (copyright attribution)
    source_label VARCHAR(200) NULL,  -- REQUIRED on image blocks: the visible credit shown under the image
    meta         VARCHAR(60)  NULL,  -- heading h2/h3, code language, list ul/ol, callout info/tip/warn
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_blocks_topic FOREIGN KEY (topic_id) REFERENCES lms_topics(id) ON DELETE CASCADE,
    KEY idx_lms_blocks_topic (topic_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One question bank for all three phases. Pre/post never overlap because 'phase'
-- is a single column: a question belongs to exactly one bank.
CREATE TABLE IF NOT EXISTS lms_questions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id      INT UNSIGNED NOT NULL,
    topic_id      INT UNSIGNED NULL,    -- NOT NULL in practice for phase='review', always NULL for pre/post
    phase         ENUM('review','pre','post') NOT NULL,
    slug          VARCHAR(64) NULL,     -- set by the seeder, NULL for admin-created (MariaDB allows many NULLs in a unique key)
    question_text TEXT NOT NULL,
    explanation   TEXT NULL,            -- revealed on the result page only
    is_active     TINYINT(1) NOT NULL DEFAULT 1,  -- inactive stays in history but is excluded from new draws
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_questions_level FOREIGN KEY (level_id) REFERENCES lms_levels(id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_questions_topic FOREIGN KEY (topic_id) REFERENCES lms_topics(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_lms_questions_slug (level_id, phase, slug),
    KEY idx_lms_questions_bank  (level_id, phase, is_active),
    KEY idx_lms_questions_topic (topic_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answer options. sort_order is the authoring order only — the display order is
-- shuffled per attempt and lives in lms_attempt_questions.choice_order.
CREATE TABLE IF NOT EXISTS lms_choices (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    choice_text VARCHAR(500) NOT NULL,
    is_correct  TINYINT(1) NOT NULL DEFAULT 0,  -- note: default 0, deliberately unlike the house is_* default of 1
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_choices_question FOREIGN KEY (question_id) REFERENCES lms_questions(id) ON DELETE CASCADE,
    KEY idx_lms_choices_question (question_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A quiz attempt. Only the raw counts are stored — the percentage, pass/fail and
-- every unlock decision are derived at read time by LmsProgress.
CREATE TABLE IF NOT EXISTS lms_attempts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    level_id       INT UNSIGNED NOT NULL,
    topic_id       INT UNSIGNED NULL,                  -- set for phase='review', NULL for pre/post
    phase          ENUM('review','pre','post') NOT NULL,
    question_count TINYINT UNSIGNED NOT NULL,          -- how many were actually drawn (the bank may change later)
    correct_count  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- when the attempt was started
    submitted_at   DATETIME NULL,                      -- NULL = still open, and an open attempt blocks a fresh draw
    CONSTRAINT fk_lms_attempts_user  FOREIGN KEY (user_id)  REFERENCES users(id)      ON DELETE CASCADE,
    CONSTRAINT fk_lms_attempts_level FOREIGN KEY (level_id) REFERENCES lms_levels(id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_attempts_topic FOREIGN KEY (topic_id) REFERENCES lms_topics(id) ON DELETE CASCADE,
    KEY idx_lms_attempts_best (user_id, level_id, phase, submitted_at),
    KEY idx_lms_attempts_open (user_id, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The drawn question set for one attempt, plus the student's frozen answer.
-- selected_choice_id deliberately has NO foreign key and is_correct is graded once
-- at submit time: an answer is a point-in-time historical record, so the admin can
-- freely rewrite a question's choices later without corrupting past results.
CREATE TABLE IF NOT EXISTS lms_attempt_questions (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id         INT UNSIGNED NOT NULL,
    question_id        INT UNSIGNED NOT NULL,
    choice_order       VARCHAR(60) NOT NULL,  -- comma-joined lms_choices.id in this attempt's shuffled display order
    selected_choice_id INT UNSIGNED NULL,     -- no FK on purpose, see above
    is_correct         TINYINT(1) NOT NULL DEFAULT 0,
    answered_at        DATETIME NULL,
    sort_order         TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 1..N question position in this attempt
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_aq_attempt  FOREIGN KEY (attempt_id)  REFERENCES lms_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_lms_aq_question FOREIGN KEY (question_id) REFERENCES lms_questions(id),
    UNIQUE KEY uniq_lms_aq_pos (attempt_id, sort_order),
    KEY idx_lms_aq_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promotion requests (ภารกิจพิสูจน์ทักษะ). Append-only: a resubmission inserts a new
-- row rather than editing the old one, so the newest row for (user_id, level_id) is
-- the live request and the rest is a free audit trail.
CREATE TABLE IF NOT EXISTS lms_promotions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    level_id         INT UNSIGNED NOT NULL,
    mission_text     TEXT NOT NULL,
    status           ENUM('pending','approved','rejected','revise') NOT NULL DEFAULT 'pending',
    admin_feedback   TEXT NULL,              -- Thai feedback shown verbatim to the student
    granted_group_id INT UNSIGNED NULL,      -- the group actually assigned on approval, so history stays truthful
    reviewed_by      INT UNSIGNED NULL,
    reviewed_at      DATETIME NULL,          -- NULL = still in the review queue
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_promotions_user  FOREIGN KEY (user_id)          REFERENCES users(id)       ON DELETE CASCADE,
    CONSTRAINT fk_lms_promotions_level FOREIGN KEY (level_id)         REFERENCES lms_levels(id)  ON DELETE CASCADE,
    CONSTRAINT fk_lms_promotions_group FOREIGN KEY (granted_group_id) REFERENCES user_groups(id) ON DELETE SET NULL,
    CONSTRAINT fk_lms_promotions_admin FOREIGN KEY (reviewed_by)      REFERENCES users(id)       ON DELETE SET NULL,
    KEY idx_lms_promotions_latest (user_id, level_id, id),
    KEY idx_lms_promotions_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mission attachments, mirroring booking_issue_files.
CREATE TABLE IF NOT EXISTS lms_promotion_files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promotion_id  INT UNSIGNED NOT NULL,
    filename      VARCHAR(255) NOT NULL,  -- bare filename under uploads/lms/missions/, never a path
    original_name VARCHAR(255) NULL,      -- what the student called it, for display only
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lms_pf_promotion FOREIGN KEY (promotion_id) REFERENCES lms_promotions(id) ON DELETE CASCADE,
    KEY idx_lms_pf_promotion (promotion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
