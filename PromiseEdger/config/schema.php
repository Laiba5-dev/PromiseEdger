<?php
// schema.php - database ko khud upgrade karta hai (purane data ko chhere bina). Safe to run many times.

const SCHEMA_VERSION = 2;

function db_has_table(mysqli $c, string $t): bool {
    return (bool)db_val($c, "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1", "s", [$t]);
}
function db_has_column(mysqli $c, string $t, string $col): bool {
    return (bool)db_val($c, "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1", "ss", [$t, $col]);
}
function db_has_index(mysqli $c, string $t, string $idx): bool {
    return (bool)db_val($c, "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1", "ss", [$t, $idx]);
}
/** true agar column abhi add hua */
function db_add_column(mysqli $c, string $t, string $col, string $ddl): bool {
    if (db_has_column($c, $t, $col)) return false;
    $c->query("ALTER TABLE `$t` ADD COLUMN `$col` $ddl");
    return true;
}
function db_add_index(mysqli $c, string $t, string $name, string $cols): void {
    if (!db_has_index($c, $t, $name)) $c->query("ALTER TABLE `$t` ADD INDEX `$name` ($cols)");
}

function ensureSchema(mysqli $c): void {
    if (($_SESSION['schema_v'] ?? 0) === SCHEMA_VERSION) return;

    $c->query("CREATE TABLE IF NOT EXISTS app_meta (k VARCHAR(50) PRIMARY KEY, v VARCHAR(255) NOT NULL) ENGINE=InnoDB");
    $v = (int)db_val($c, "SELECT v FROM app_meta WHERE k = 'schema_version'");
    if ($v >= SCHEMA_VERSION) { $_SESSION['schema_v'] = SCHEMA_VERSION; return; }

    // Ek waqt me ek hi request migrate kare
    $c->query("SELECT GET_LOCK('pe_migrate', 15)");
    try {
        $v = (int)db_val($c, "SELECT v FROM app_meta WHERE k = 'schema_version'");
        if ($v < SCHEMA_VERSION) runMigrations($c);
    } finally {
        $c->query("SELECT RELEASE_LOCK('pe_migrate')");
    }
    $_SESSION['schema_v'] = SCHEMA_VERSION;
}

function runMigrations(mysqli $c): void {
    // ---- base tables (agar db.sql nahi chali) ----
    $c->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        profile_picture VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $c->query("CREATE TABLE IF NOT EXISTS promises (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        visibility ENUM('public','private') NOT NULL DEFAULT 'public',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $c->query("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promise_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ---- users: email verification ----
    if (db_add_column($c, 'users', 'email_verified_at', "DATETIME NULL DEFAULT NULL AFTER profile_picture")) {
        // purane users ko verified maan lo (warna wo lock ho jayenge)
        $c->query("UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL");
    }
    db_add_index($c, 'users', 'idx_users_created', 'created_at');

    // ---- promises: status, progress, deadline, category, streak ----
    db_add_column($c, 'promises', 'category', "VARCHAR(20) NOT NULL DEFAULT 'other' AFTER visibility");
    db_add_column($c, 'promises', 'status', "ENUM('in_progress','done','missed') NOT NULL DEFAULT 'in_progress' AFTER category");
    db_add_column($c, 'promises', 'progress', "TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status");
    db_add_column($c, 'promises', 'due_date', "DATE NULL DEFAULT NULL AFTER progress");
    db_add_column($c, 'promises', 'completed_at', "DATETIME NULL DEFAULT NULL AFTER due_date");
    db_add_column($c, 'promises', 'streak_count', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER completed_at");
    db_add_column($c, 'promises', 'best_streak', "INT UNSIGNED NOT NULL DEFAULT 0 AFTER streak_count");
    db_add_column($c, 'promises', 'last_checkin', "DATE NULL DEFAULT NULL AFTER best_streak");
    db_add_column($c, 'promises', 'reminder_sent_on', "DATE NULL DEFAULT NULL AFTER last_checkin");
    db_add_column($c, 'promises', 'updated_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    db_add_index($c, 'promises', 'idx_p_user_created', 'user_id, created_at');
    db_add_index($c, 'promises', 'idx_p_vis_created', 'visibility, created_at');
    db_add_index($c, 'promises', 'idx_p_status_due', 'status, due_date');
    db_add_index($c, 'promises', 'idx_p_done', 'status, completed_at');
    db_add_index($c, 'promises', 'idx_p_category', 'category');

    // ---- comments ----
    db_add_index($c, 'comments', 'idx_c_promise_created', 'promise_id, created_at');
    db_add_index($c, 'comments', 'idx_c_user', 'user_id');

    // ---- reactions (likes ki jagah) ----
    $c->query("CREATE TABLE IF NOT EXISTS reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promise_id INT NOT NULL,
        user_id INT NOT NULL,
        type VARCHAR(16) NOT NULL DEFAULT 'like',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reaction (promise_id, user_id),
        KEY idx_r_promise_type (promise_id, type),
        KEY idx_r_user (user_id),
        FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    if (db_has_table($c, 'likes')) {
        $c->query("INSERT IGNORE INTO reactions (promise_id, user_id, type, created_at)
                   SELECT promise_id, user_id, 'like', created_at FROM likes");
    }

    // ---- daily check-ins (streaks) ----
    $c->query("CREATE TABLE IF NOT EXISTS checkins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promise_id INT NOT NULL,
        user_id INT NOT NULL,
        checkin_date DATE NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_checkin (promise_id, checkin_date),
        KEY idx_ch_user_date (user_id, checkin_date),
        FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ---- follows ----
    $c->query("CREATE TABLE IF NOT EXISTS follows (
        follower_id INT NOT NULL,
        following_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, following_id),
        KEY idx_f_following (following_id),
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ---- notifications ----
    $c->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        actor_id INT NULL,
        type VARCHAR(24) NOT NULL,
        promise_id INT NULL,
        title VARCHAR(255) NULL,
        data VARCHAR(100) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_n_user (user_id, is_read, id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    // ---- reports ----
    $c->query("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promise_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reason VARCHAR(20) NOT NULL,
        details VARCHAR(500) NULL,
        status ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_report (promise_id, reporter_id),
        KEY idx_rep_status (status, created_at),
        FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    // ---- password reset / email verify tokens ----
    $c->query("CREATE TABLE IF NOT EXISTS user_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('verify','reset') NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_token (token_hash),
        KEY idx_t_user_type (user_id, type),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    db_exec($c, "REPLACE INTO app_meta (k, v) VALUES ('schema_version', ?)", "s", [(string)SCHEMA_VERSION]);
}
