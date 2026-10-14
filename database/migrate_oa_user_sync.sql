-- ONE-RVC User-sync API (includes/OaUserSync.php).
-- oa_users is a mirror of the ONE-RVC directory, upserted by ONE-RVC's own user id
-- (the same id verify_token returns, i.e. users.sso_user_id). It is deliberately a
-- separate table rather than rows in `users`: accounts here still need admin approval
-- plus a major/subject that ONE-RVC doesn't provide, so the sync never provisions
-- logins — it links existing accounts by email and blocks linked accounts whose
-- ONE-RVC status is "suspended" (derived at read time, no flag copied into users).
CREATE TABLE IF NOT EXISTS oa_users (
    oa_user_id        VARCHAR(64)  NOT NULL PRIMARY KEY,
    first_name        VARCHAR(150) NULL,
    last_name         VARCHAR(150) NULL,
    department        VARCHAR(200) NULL,
    user_type         VARCHAR(20)  NULL,
    status            VARCHAR(20)  NOT NULL DEFAULT 'active',
    email             VARCHAR(150) NULL,
    google_email      VARCHAR(150) NULL,
    username          VARCHAR(100) NULL,
    remote_updated_at DATETIME     NULL,
    synced_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_oa_users_email (email),
    KEY idx_oa_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per sync run. The `since` for the next incremental run is the server_time of
-- the newest run with ok = 1 — a failed or half-finished run never advances it.
CREATE TABLE IF NOT EXISTS oa_sync_runs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mode         VARCHAR(12)  NOT NULL,
    since_used   VARCHAR(40)  NULL,
    server_time  VARCHAR(40)  NULL,
    ok           TINYINT(1)   NOT NULL DEFAULT 0,
    pages        INT UNSIGNED NOT NULL DEFAULT 0,
    added        INT UNSIGNED NOT NULL DEFAULT 0,
    updated      INT UNSIGNED NOT NULL DEFAULT 0,
    suspended    INT UNSIGNED NOT NULL DEFAULT 0,
    linked       INT UNSIGNED NOT NULL DEFAULT 0,
    error        VARCHAR(500) NULL,
    started_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at  DATETIME     NULL,
    KEY idx_oa_sync_runs_ok (ok, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
