<?php
// config.php - configuration + shared helpers for PromiseEdger

// Optional local overrides (DB password, mail settings...). Create config/config.local.php to use it.
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
function cfg_default($name, $value) {
    if (!defined($name)) define($name, $value);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- Environment ----------
cfg_default('APP_DEBUG', true);          // live server par false kar dena
cfg_default('APP_NAME', 'PromiseEdger');
cfg_default('APP_TZ', 'Asia/Karachi');
cfg_default('APP_URL', '');              // e.g. https://example.com/promiseedger (khali = auto detect)
date_default_timezone_set(APP_TZ);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ---------- Database ----------
cfg_default('DB_HOST', 'localhost');
cfg_default('DB_USER', 'root');
cfg_default('DB_PASS', 'Laiba@123');
cfg_default('DB_NAME', 'promiseedger');

// ---------- Email ----------
// MAIL_DRIVER: 'log'  -> email file me save hoti hai (storage/mail.log), debug me link screen par bhi dikhta hai (local testing)
//              'smtp' -> real SMTP (Gmail: smtp.gmail.com, port 587, tls, App Password)
//              'mail' -> PHP mail() (hosting par)
cfg_default('MAIL_DRIVER', 'log');
cfg_default('MAIL_HOST', 'smtp.gmail.com');
cfg_default('MAIL_PORT', 587);
cfg_default('MAIL_ENCRYPTION', 'tls');   // tls | ssl | none
cfg_default('MAIL_USER', '');
cfg_default('MAIL_PASS', '');
cfg_default('MAIL_FROM', 'no-reply@promiseedger.local');
cfg_default('MAIL_FROM_NAME', 'PromiseEdger');
// true = email verify kiye bina post/comment/react nahi kar sakte
cfg_default('REQUIRE_VERIFIED_EMAIL', true);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (Throwable $e) {
    die(APP_DEBUG ? "Connection failed: " . $e->getMessage() : "Database connection failed.");
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '" . (new DateTime('now', new DateTimeZone(APP_TZ)))->format('P') . "'");

require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/mailer.php';

try {
    ensureSchema($conn);
} catch (Throwable $e) {
    die(APP_DEBUG ? "Database upgrade failed: " . htmlspecialchars($e->getMessage()) : "Database upgrade failed.");
}

// ---------- Constants used across the app ----------
const PE_CATEGORIES = [
    'health' => 'bi-heart-pulse',
    'study'  => 'bi-mortarboard',
    'money'  => 'bi-wallet2',
    'work'   => 'bi-briefcase',
    'family' => 'bi-people',
    'other'  => 'bi-stars',
];
const PE_STATUSES  = ['in_progress', 'done', 'missed'];
const PE_REACTIONS = ['like' => '👍', 'love' => '❤️', 'fire' => '🔥', 'clap' => '👏', 'strong' => '💪', 'party' => '🎉'];
const PE_REPORT_REASONS = ['spam', 'inappropriate', 'misleading', 'harassment', 'other'];
const PE_STREAK_MILESTONES = [3, 7, 14, 30, 60, 100];

// ========== SMALL HELPERS ==========

function escapeHtml($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function today(): string {
    return date('Y-m-d');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_ok(): bool {
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}

function json_out(bool $ok, array $extra = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

function appUrl(string $path = ''): string {
    if (APP_URL !== '') return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/' . ltrim($path, '/');
}

/** Wapas usi page par bhejo jahan se request aayi (sirf apni site ke andar). */
function redirect_back(string $fallback = 'dashboard.php'): void {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    $dest = $fallback;
    if ($ref && $host && strcasecmp($host, parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: '') === 0) {
        $dest = $ref;
    }
    header("Location: " . $dest);
    exit();
}

// ----- tiny prepared-statement helpers -----
function db_exec(mysqli $c, string $sql, string $types = '', array $params = []): mysqli_stmt {
    $stmt = $c->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}
function db_all(mysqli $c, string $sql, string $types = '', array $params = []): array {
    return db_exec($c, $sql, $types, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function db_one(mysqli $c, string $sql, string $types = '', array $params = []): ?array {
    return db_exec($c, $sql, $types, $params)->get_result()->fetch_assoc() ?: null;
}
function db_val(mysqli $c, string $sql, string $types = '', array $params = []) {
    $row = db_exec($c, $sql, $types, $params)->get_result()->fetch_row();
    return $row ? $row[0] : null;
}

// ========== AUTHENTICATION ==========

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function getCurrentUserId() {
    return $_SESSION['user']['id'] ?? null;
}

function isAdmin($conn = null) {
    if (!isset($_SESSION['user']['id'])) return false;
    static $cached = null;
    if ($cached !== null) return $cached;
    if ($conn === null) $conn = $GLOBALS['conn'];

    $row = db_one($conn, "SELECT role FROM users WHERE id = ?", "i", [(int)$_SESSION['user']['id']]);
    $cached = ($row && ($row['role'] ?? 'user') === 'admin');
    if ($row) $_SESSION['user']['role'] = $row['role'] ?? 'user';
    return $cached;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = t('err_login_required');
        header("Location: login.php");
        exit();
    }
}

function requireAdmin($conn) {
    if (!isAdmin($conn)) {
        header('Location: dashboard.php');
        exit();
    }
}

function getCurrentUser($conn) {
    if (!isLoggedIn()) return null;
    static $cache = [];
    $id = (int)getCurrentUserId();
    if (!isset($cache[$id])) {
        $cache[$id] = db_one($conn, "SELECT id, name, email, role, profile_picture, email_verified_at FROM users WHERE id = ?", "i", [$id]);
    }
    return $cache[$id];
}

function isVerified($conn): bool {
    if (!REQUIRE_VERIFIED_EMAIL) return true;
    $u = getCurrentUser($conn);
    return $u && !empty($u['email_verified_at']);
}

/** AJAX/JSON endpoints ke liye: login + csrf + (optional) verified check. */
function api_guard($conn, bool $needVerified = true): int {
    if (!isLoggedIn()) json_out(false, ['message' => t('err_login_first')], 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) json_out(false, ['message' => t('err_invalid_request')], 403);
    if (!getCurrentUser($conn)) json_out(false, ['message' => t('err_login_first')], 401);
    if ($needVerified && !isVerified($conn)) json_out(false, ['message' => t('err_verify_first')], 403);
    return (int)getCurrentUserId();
}

// ========== USERS ==========

function getAllUsers($conn) {
    return db_all($conn, "SELECT u.*,
            (SELECT COUNT(*) FROM promises WHERE user_id = u.id) AS promise_count,
            (SELECT COUNT(*) FROM promises WHERE user_id = u.id AND status = 'done') AS done_count,
            (SELECT COUNT(*) FROM reactions r JOIN promises p ON r.promise_id = p.id WHERE p.user_id = u.id) AS reactions_received,
            (SELECT COUNT(*) FROM follows WHERE following_id = u.id) AS followers
            FROM users u ORDER BY u.created_at DESC");
}

function toggleAdminStatus($conn, $user_id) {
    if (!isAdmin($conn)) return false;
    if ($user_id == ($_SESSION['user']['id'] ?? 0)) return false;
    $user = db_one($conn, "SELECT role FROM users WHERE id = ?", "i", [$user_id]);
    if (!$user) return false;
    $new_role = (($user['role'] ?? 'user') === 'admin') ? 'user' : 'admin';
    db_exec($conn, "UPDATE users SET role = ? WHERE id = ?", "si", [$new_role, $user_id]);
    return true;
}

function getUserStats($conn, $uid, $publicOnly = false) {
    $vis = $publicOnly ? "AND visibility = 'public'" : "";
    $p = db_one($conn, "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'done'), 0) AS done,
            COALESCE(SUM(status = 'in_progress'), 0) AS active,
            COALESCE(SUM(status = 'missed'), 0) AS missed,
            COALESCE(SUM(visibility = 'private'), 0) AS private_count,
            COALESCE(MAX(best_streak), 0) AS best_streak
            FROM promises WHERE user_id = ? $vis", "i", [$uid]);
    $r = (int)db_val($conn, "SELECT COUNT(*) FROM reactions r JOIN promises p ON r.promise_id = p.id WHERE p.user_id = ? " . ($publicOnly ? "AND p.visibility = 'public'" : ""), "i", [$uid]);
    $c = (int)db_val($conn, "SELECT COUNT(*) FROM comments c JOIN promises p ON c.promise_id = p.id WHERE p.user_id = ? " . ($publicOnly ? "AND p.visibility = 'public'" : ""), "i", [$uid]);
    $followers = (int)db_val($conn, "SELECT COUNT(*) FROM follows WHERE following_id = ?", "i", [$uid]);
    $following = (int)db_val($conn, "SELECT COUNT(*) FROM follows WHERE follower_id = ?", "i", [$uid]);
    $checkins = (int)db_val($conn, "SELECT COUNT(*) FROM checkins WHERE user_id = ?", "i", [$uid]);
    return [
        'promises' => (int)$p['total'], 'done' => (int)$p['done'], 'active' => (int)$p['active'], 'missed' => (int)$p['missed'],
        'private' => (int)$p['private_count'], 'best_streak' => (int)$p['best_streak'],
        'reactions' => $r, 'comments' => $c, 'followers' => $followers, 'following' => $following, 'checkins' => $checkins,
    ];
}

// ========== FOLLOW ==========

function isFollowing($conn, int $me, int $other): bool {
    return (bool)db_val($conn, "SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?", "ii", [$me, $other]);
}

// ========== PROMISES ==========

/** Deadline nikal chuke in-progress promises ko "missed" bana do (aur owner ko notify karo). */
function syncMissed($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    $rows = db_all($conn, "SELECT id, user_id, title FROM promises WHERE status = 'in_progress' AND due_date IS NOT NULL AND due_date < CURDATE()");
    if (!$rows) return;
    foreach ($rows as $r) {
        notify($conn, (int)$r['user_id'], null, 'missed', (int)$r['id'], null, 0, $r['title']);
    }
    $conn->query("UPDATE promises SET status = 'missed' WHERE status = 'in_progress' AND due_date IS NOT NULL AND due_date < CURDATE()");
}

/** Din bachne ka hisab: ['days' => int|null, 'class' => ..., 'label' => ...] */
function dueInfo(array $p): ?array {
    if (empty($p['due_date'])) return null;
    if (($p['status'] ?? '') === 'done') return null;   // done promise par progress bar "Completed on ..." dikhata hai
    $days = (int)(new DateTime(today()))->diff(new DateTime($p['due_date']))->format('%r%a');
    if ($days < 0) {
        $n = abs($days);
        return ['days' => $days, 'class' => 'bad', 'icon' => 'bi-exclamation-circle', 'label' => $n === 1 ? t('overdue_1') : t('overdue_n', $n)];
    }
    if ($days === 0) return ['days' => 0, 'class' => 'warn', 'icon' => 'bi-alarm', 'label' => t('due_today')];
    if ($days === 1) return ['days' => 1, 'class' => 'warn', 'icon' => 'bi-alarm', 'label' => t('day_left_1')];
    return ['days' => $days, 'class' => $days <= 3 ? 'warn' : 'calm', 'icon' => $days <= 3 ? 'bi-alarm' : 'bi-calendar-event', 'label' => t('days_left', $days)];
}

/** Streak: agar kal ya aaj check-in hua to chal rahi hai, warna 0 */
function effectiveStreak(array $p): int {
    if (empty($p['last_checkin'])) return 0;
    $d = (int)(new DateTime($p['last_checkin']))->diff(new DateTime(today()))->days;
    return $d <= 1 ? (int)$p['streak_count'] : 0;
}

function streakTier(int $n): int {
    return $n >= 30 ? 3 : ($n >= 7 ? 2 : ($n >= 3 ? 1 : 0));
}

/**
 * Status/progress ke rules ek jagah. Return [status, progress, completed_at, error|null]
 */
function normalizeStatus(string $status, int $progress, ?string $due, ?string $oldCompletedAt): array {
    if (!in_array($status, PE_STATUSES, true)) $status = 'in_progress';
    $progress = max(0, min(100, $progress));
    if ($status === 'done') $progress = 100;
    if ($progress >= 100 && $status !== 'missed') $status = 'done';
    $err = null;
    if ($status === 'in_progress' && $due && $due < today()) $err = t('err_deadline_past_status');
    $completed = $status === 'done' ? ($oldCompletedAt ?: date('Y-m-d H:i:s')) : null;
    return [$status, $progress, $completed, $err];
}

function parseDueDate(?string $raw, &$error): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $raw);
    if (!$d || $d->format('Y-m-d') !== $raw) { $error = t('err_bad_date'); return null; }
    return $raw;
}

function sanitizeCategory($c): string {
    return array_key_exists($c, PE_CATEGORIES) ? $c : 'other';
}

/**
 * Feed query. $o: feed(all|following|mine|user), user_id, category, status, q, id, limit, offset
 * Hamesha: public promises ya khud ke promises.
 */
function queryPromises($conn, int $uid, array $o = []): array {
    if (!empty($o['all_visible'])) {          // admin kisi ki bhi private promise dekh sake (profile page)
        $where = ["1 = 1"]; $types = ""; $params = [];
    } else {
        $where = ["(p.visibility = 'public' OR p.user_id = ?)"]; $types = "i"; $params = [$uid];
    }
    $feed = $o['feed'] ?? 'all';

    if (!empty($o['id'])) { $where[] = "p.id = ?"; $types .= "i"; $params[] = (int)$o['id']; }
    if ($feed === 'following') {
        $where[] = "p.visibility = 'public' AND p.user_id IN (SELECT following_id FROM follows WHERE follower_id = ?)";
        $types .= "i"; $params[] = $uid;
    } elseif ($feed === 'mine') {
        $where[] = "p.user_id = ?"; $types .= "i"; $params[] = $uid;
    } elseif ($feed === 'user' && !empty($o['user_id'])) {
        $where[] = "p.user_id = ?"; $types .= "i"; $params[] = (int)$o['user_id'];
    }
    if (!empty($o['category']) && array_key_exists($o['category'], PE_CATEGORIES)) {
        $where[] = "p.category = ?"; $types .= "s"; $params[] = $o['category'];
    }
    if (!empty($o['status']) && in_array($o['status'], PE_STATUSES, true)) {
        $where[] = "p.status = ?"; $types .= "s"; $params[] = $o['status'];
    }
    if (!empty($o['q'])) {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $o['q']) . '%';
        $where[] = "(p.title LIKE ? OR p.description LIKE ? OR u.name LIKE ?)";
        $types .= "sss"; array_push($params, $like, $like, $like);
    }

    $limit = max(1, min(50, (int)($o['limit'] ?? 10)));
    $offset = max(0, (int)($o['offset'] ?? 0));
    $sql = "SELECT p.*, u.name AS author_name, u.profile_picture AS author_pic,
            (SELECT COUNT(*) FROM reactions r WHERE r.promise_id = p.id) AS reaction_count,
            (SELECT COUNT(*) FROM comments c WHERE c.promise_id = p.id) AS comment_count,
            (SELECT r.type FROM reactions r WHERE r.promise_id = p.id AND r.user_id = ?) AS my_reaction
            FROM promises p JOIN users u ON u.id = p.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.created_at DESC, p.id DESC LIMIT $limit OFFSET $offset";
    // my_reaction ka param sabse pehle aata hai
    array_unshift($params, $uid);
    $types = "i" . $types;
    return db_all($conn, $sql, $types, $params);
}

function getPromiseById($conn, $promise_id, $user_id = null) {
    $rows = queryPromises($conn, (int)$user_id, ['id' => (int)$promise_id, 'limit' => 1]);
    return $rows[0] ?? null;
}

/** Cards ke liye extra data ek hi baar me (N+1 queries se bachao). */
function loadCardExtras($conn, array $promises, int $uid): array {
    $x = ['reactions' => [], 'comments' => [], 'week' => [], 'following' => [], 'reported' => []];
    if (!$promises) return $x;
    $ids = implode(',', array_map('intval', array_column($promises, 'id')));
    $authors = implode(',', array_unique(array_map('intval', array_column($promises, 'user_id'))));

    foreach (db_all($conn, "SELECT promise_id, type, COUNT(*) AS c FROM reactions WHERE promise_id IN ($ids) GROUP BY promise_id, type") as $r) {
        $x['reactions'][$r['promise_id']][$r['type']] = (int)$r['c'];
    }
    foreach (db_all($conn, "SELECT c.*, u.name AS author_name, u.profile_picture AS author_pic
            FROM comments c JOIN users u ON u.id = c.user_id WHERE c.promise_id IN ($ids) ORDER BY c.created_at ASC, c.id ASC") as $c) {
        $x['comments'][$c['promise_id']][] = $c;
    }
    foreach (db_all($conn, "SELECT promise_id, checkin_date FROM checkins WHERE promise_id IN ($ids) AND checkin_date >= CURDATE() - INTERVAL 6 DAY") as $r) {
        $x['week'][$r['promise_id']][] = $r['checkin_date'];
    }
    foreach (db_all($conn, "SELECT following_id FROM follows WHERE follower_id = ? AND following_id IN ($authors)", "i", [$uid]) as $r) {
        $x['following'][(int)$r['following_id']] = true;
    }
    foreach (db_all($conn, "SELECT promise_id FROM reports WHERE reporter_id = ? AND promise_id IN ($ids)", "i", [$uid]) as $r) {
        $x['reported'][(int)$r['promise_id']] = true;
    }
    return $x;
}

function reactionCounts($conn, int $promise_id): array {
    $out = [];
    foreach (db_all($conn, "SELECT type, COUNT(*) AS c FROM reactions WHERE promise_id = ? GROUP BY type", "i", [$promise_id]) as $r) {
        $out[$r['type']] = (int)$r['c'];
    }
    return $out;
}

// ========== COMMENTS ==========

function addComment($conn, $promise_id, $user_id, $comment) {
    db_exec($conn, "INSERT INTO comments (promise_id, user_id, comment) VALUES (?, ?, ?)", "iis", [$promise_id, $user_id, $comment]);
    return (int)$conn->insert_id;
}

function deleteComment($conn, $comment_id, $user_id) {
    if (!isAdmin($conn)) {
        db_exec($conn, "DELETE FROM comments WHERE id = ? AND user_id = ?", "ii", [$comment_id, $user_id]);
    } else {
        db_exec($conn, "DELETE FROM comments WHERE id = ?", "i", [$comment_id]);
    }
}

// ========== NOTIFICATIONS ==========

function notify($conn, int $to, ?int $actor, string $type, ?int $promiseId = null, ?string $data = null, int $dedupeMinutes = 0, ?string $title = null): void {
    if ($actor !== null && $actor === $to) return;
    if ($dedupeMinutes > 0) {
        $exists = db_val($conn, "SELECT 1 FROM notifications WHERE user_id = ? AND actor_id <=> ? AND type = ? AND promise_id <=> ?
                 AND created_at > NOW() - INTERVAL $dedupeMinutes MINUTE LIMIT 1", "iisi", [$to, $actor, $type, $promiseId]);
        if ($exists) return;
    }
    if ($title !== null) $title = mb_substr($title, 0, 255);
    db_exec($conn, "INSERT INTO notifications (user_id, actor_id, type, promise_id, title, data) VALUES (?, ?, ?, ?, ?, ?)",
        "iisiss", [$to, $actor, $type, $promiseId, $title, $data]);
}

function unreadCount($conn, int $uid): int {
    return (int)db_val($conn, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", "i", [$uid]);
}

/** Din me ek baar: due date ke qareeb wale promises ka reminder. */
function runDailyReminders($conn, int $uid): void {
    if (($_SESSION['reminders_day'] ?? '') === today()) return;
    $_SESSION['reminders_day'] = today();
    $rows = db_all($conn, "SELECT id, title, DATEDIFF(due_date, CURDATE()) AS d FROM promises
        WHERE user_id = ? AND status = 'in_progress' AND due_date IS NOT NULL
        AND due_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY
        AND (reminder_sent_on IS NULL OR reminder_sent_on < CURDATE())", "i", [$uid]);
    foreach ($rows as $r) {
        notify($conn, $uid, null, 'reminder', (int)$r['id'], (string)$r['d'], 0, $r['title']);
        db_exec($conn, "UPDATE promises SET reminder_sent_on = CURDATE() WHERE id = ?", "i", [(int)$r['id']]);
    }
}

// ========== DATES ==========

function formatDate($timestamp) {
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($date);

    if ($diff->days == 0) {
        if ($diff->h == 0) {
            if ($diff->i < 1) return t('just_now');
            return t('min_ago', $diff->i);
        }
        return t('hours_ago', $diff->h);
    } elseif ($diff->days == 1) {
        return t('yesterday');
    } elseif ($diff->days < 7) {
        return t('days_ago', $diff->days);
    }
    return fmtDate($timestamp);
}

function fmtDate($timestamp, bool $withYear = true): string {
    $d = new DateTime($timestamp);
    $months = t('months_short');
    $m = $months[(int)$d->format('n') - 1] ?? $d->format('M');
    return $d->format('j') . ' ' . $m . ($withYear ? ' ' . $d->format('Y') : '');
}

// Har request par: deadline guzar chuke promises "missed" ho jayen
syncMissed($conn);
