<?php
// File: layout.php - shared layout helpers (head, nav, flash, footer, avatar, notifications)
require_once __DIR__ . '/partials/promise_card.php';
require_once __DIR__ . '/partials/promise_form.php';

function avatar_html($u, $size = 40) {
    $size = (int)$size;
    $pic = $u['profile_picture'] ?? ($u['author_pic'] ?? '');
    if (!empty($pic)) {
        return '<img src="' . escapeHtml($pic) . '" alt="" class="avatar" style="width:' . $size . 'px;height:' . $size . 'px;">';
    }
    $name = $u['name'] ?? ($u['author_name'] ?? '?');
    $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
    return '<span class="avatar" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size / 2.3) . 'px;">'
        . escapeHtml($initial) . '</span>';
}

function page_head($title = 'PromiseEdger', $bodyClass = '') {
    $v = @filemtime(__DIR__ . '/style.css') ?: 1;
    $ur = is_rtl();
    ?>
<!DOCTYPE html>
<html lang="<?php echo lang(); ?>" dir="<?php echo dir_attr(); ?>" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHtml($title); ?></title>
    <meta name="theme-color" content="#C5A253">
    <script>
    (function () {
        var t = 'light';
        try {
            t = localStorage.getItem('pe-theme') || (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } catch (e) {}
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if ($ur): ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Naskh+Arabic:wght@400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo $v; ?>">
</head>
<body class="<?php echo escapeHtml(trim($bodyClass . ' lang-' . lang())); ?>">
<div class="glow" aria-hidden="true"><span></span><span></span><span></span></div>
<?php
}

function theme_toggle() {
    ?>
<button type="button" class="icon-btn" id="themeToggle" aria-label="<?php echo escapeHtml(t('toggle_theme')); ?>" title="<?php echo escapeHtml(t('toggle_theme')); ?>">
    <i class="bi bi-sun-fill ico-sun"></i><i class="bi bi-moon-stars-fill ico-moon"></i>
</button>
    <?php
}

function lang_toggle() {
    $to = is_rtl() ? 'en' : 'ur';
    $label = is_rtl() ? 'English' : 'اردو';
    $back = urlencode(basename($_SERVER['SCRIPT_NAME']) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    ?>
<a class="lang-btn" href="set_lang.php?l=<?php echo $to; ?>&back=<?php echo $back; ?>" title="<?php echo escapeHtml(t('language')); ?>">
    <i class="bi bi-translate"></i> <span><?php echo $label; ?></span>
</a>
    <?php
}

// ---------- Notifications text ----------
function notif_icon(string $type, ?string $data): string {
    switch ($type) {
        case 'reaction': return PE_REACTIONS[$data ?? 'like'] ?? '👍';
        case 'comment': return '<i class="bi bi-chat-dots-fill"></i>';
        case 'follow': return '<i class="bi bi-person-plus-fill"></i>';
        case 'reminder': return '<i class="bi bi-alarm-fill"></i>';
        case 'missed': return '<i class="bi bi-exclamation-circle-fill"></i>';
        case 'streak': return '🔥';
        case 'report_resolved': return '<i class="bi bi-shield-check"></i>';
        case 'removed': return '<i class="bi bi-slash-circle-fill"></i>';
    }
    return '<i class="bi bi-bell-fill"></i>';
}

function notif_text(array $n): string {
    $actor = '<strong>' . escapeHtml($n['actor_name'] ?? t('someone')) . '</strong>';
    $title = '<em>' . escapeHtml($n['title'] ?? '') . '</em>';
    switch ($n['type']) {
        case 'reaction': return t('n_reaction', $actor, PE_REACTIONS[$n['data'] ?? 'like'] ?? '👍', $title);
        case 'comment':  return t('n_comment', $actor, $title);
        case 'follow':   return t('n_follow', $actor);
        case 'reminder':
            $d = (int)($n['data'] ?? 0);
            return $d === 0 ? t('n_reminder_today', $title) : ($d === 1 ? t('n_reminder_1', $title) : t('n_reminder_n', $title, $d));
        case 'missed':   return t('n_missed', $title);
        case 'streak':   return t('n_streak', (int)($n['data'] ?? 0), $title);
        case 'removed':  return t('n_removed', $title);
        case 'report_resolved':
            return ($n['data'] ?? '') === 'dismissed' ? t('n_report_dismissed', $title) : t('n_report_resolved', $title);
    }
    return escapeHtml($n['type']);
}

function notif_url(array $n): string {
    if ($n['type'] === 'follow' && !empty($n['actor_id'])) return 'profile.php?id=' . (int)$n['actor_id'];
    if (!empty($n['promise_id'])) return 'promise.php?id=' . (int)$n['promise_id'];
    return 'notifications.php';
}

function notif_item_html(array $n): string {
    $cls = $n['is_read'] ? '' : ' unread';
    return '<a class="notif-item' . $cls . '" href="notifications.php?go=' . (int)$n['id'] . '">'
        . '<span class="notif-ico">' . notif_icon($n['type'], $n['data'] ?? null) . '</span>'
        . '<span class="notif-body"><span class="notif-text">' . notif_text($n) . '</span>'
        . '<span class="notif-time">' . escapeHtml(formatDate($n['created_at'])) . '</span></span></a>';
}

function page_nav($active = '') {
    global $conn;

    if (!isLoggedIn()) {
        ?>
<nav class="navbar navbar-expand-lg nav-x">
    <div class="container">
        <a class="brand" href="index.php"><span class="brand-mark"><i class="bi bi-patch-check-fill"></i></span> PromiseEdger</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-label="Menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="index.php#features"><?php echo t('nav_features'); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#how"><?php echo t('nav_how'); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#explore"><?php echo t('nav_explore'); ?></a></li>
            </ul>
            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <?php lang_toggle(); theme_toggle(); ?>
                <a href="login.php" class="btn btn-ghost"><?php echo t('login'); ?></a>
                <a href="register.php" class="btn btn-brand"><?php echo t('get_started'); ?></a>
            </div>
        </div>
    </div>
</nav>
        <?php
        return;
    }

    $me = getCurrentUser($conn) ?: ['name' => 'User', 'profile_picture' => null, 'email_verified_at' => 1];
    $uid = (int)getCurrentUserId();
    $admin = isAdmin($conn);
    runDailyReminders($conn, $uid);
    $unread = unreadCount($conn, $uid);
    $recent = db_all($conn, "SELECT n.*, a.name AS actor_name FROM notifications n LEFT JOIN users a ON a.id = n.actor_id
        WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 6", "i", [$uid]);

    $links = [
        ['dashboard', 'dashboard.php', 'bi-grid-1x2', t('nav_dashboard')],
        ['leaderboard', 'leaderboard.php', 'bi-trophy', t('nav_leaderboard')],
        ['add', 'add_promise.php', 'bi-plus-circle', t('nav_new_promise')],
        ['profile', 'profile.php', 'bi-person', t('nav_profile')],
    ];
    if ($admin) $links[] = ['admin', 'admin.php', 'bi-shield-lock', t('nav_admin')];
    ?>
<nav class="navbar navbar-expand-lg nav-x">
    <div class="container">
        <a class="brand" href="dashboard.php"><span class="brand-mark"><i class="bi bi-patch-check-fill"></i></span> PromiseEdger</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-label="Menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav mx-auto">
                <?php foreach ($links as $l): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active === $l[0] ? 'active' : ''; ?>" href="<?php echo $l[1]; ?>">
                            <i class="bi <?php echo $l[2]; ?>"></i> <?php echo $l[3]; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <?php lang_toggle(); theme_toggle(); ?>

                <div class="dropdown">
                    <button type="button" class="icon-btn bell <?php echo $unread ? 'has-unread' : ''; ?>" id="bellBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="<?php echo escapeHtml(t('notifications')); ?>">
                        <i class="bi bi-bell-fill"></i>
                        <span class="bell-badge <?php echo $unread ? '' : 'd-none'; ?>" id="bellCount"><?php echo $unread > 99 ? '99+' : $unread; ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-menu">
                        <div class="notif-head">
                            <strong><?php echo t('notifications'); ?></strong>
                            <button type="button" class="link-btn" id="markAllRead"><?php echo t('mark_all_read'); ?></button>
                        </div>
                        <div class="notif-list" id="notifList">
                            <?php if (!$recent): ?>
                                <div class="notif-empty"><i class="bi bi-bell-slash"></i><span><?php echo t('no_notifications'); ?></span></div>
                            <?php endif; ?>
                            <?php foreach ($recent as $n) echo notif_item_html($n); ?>
                        </div>
                        <a class="notif-foot" href="notifications.php"><?php echo t('view_all'); ?></a>
                    </div>
                </div>

                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center gap-2 user-chip" data-bs-toggle="dropdown">
                        <?php echo avatar_html($me, 34); ?>
                        <span class="d-none d-xl-inline fw-semibold"><?php echo escapeHtml($me['name']); ?></span>
                        <i class="bi bi-chevron-down small"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> <?php echo t('nav_profile'); ?></a></li>
                        <li><a class="dropdown-item" href="notifications.php"><i class="bi bi-bell"></i> <?php echo t('notifications'); ?></a></li>
                        <?php if ($admin): ?>
                            <li><a class="dropdown-item" href="admin.php"><i class="bi bi-shield-lock"></i> <?php echo t('nav_admin'); ?></a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> <?php echo t('logout'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
    <?php if (REQUIRE_VERIFIED_EMAIL && empty($me['email_verified_at'])): ?>
<div class="verify-bar">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-envelope-exclamation"></i> <?php echo t('verify_banner', escapeHtml($me['email'] ?? '')); ?></span>
        <form method="POST" action="resend_verification.php" class="m-0">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <button class="btn btn-sm btn-brand"><?php echo t('resend_verification'); ?></button>
        </form>
    </div>
</div>
    <?php endif;
}

function page_flash() {
    foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls) {
        if (!empty($_SESSION[$key])) {
            echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
                . escapeHtml($_SESSION[$key])
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION[$key]);
        }
    }
    // Local testing: email bheji nahi gayi, link yahan dikhao
    if (!empty($_SESSION['dev_mail'])) {
        $d = $_SESSION['dev_mail'];
        unset($_SESSION['dev_mail']);
        echo '<div class="alert alert-dev"><div class="fw-semibold mb-1"><i class="bi bi-wrench-adjustable"></i> ' . escapeHtml(t('dev_mode')) . '</div>'
            . '<div class="small mb-2">' . escapeHtml(t('dev_mail_note', $d['to'])) . '</div>'
            . '<a class="small" style="word-break:break-all" href="' . escapeHtml($d['link']) . '">' . escapeHtml($d['link']) . '</a></div>';
    }
}

/** JS ke liye translated strings */
function js_strings(): array {
    $keys = ['network_error', 'link_copied', 'confirm_delete_promise', 'confirm_delete_comment', 'something_wrong', 'report_sent',
        'followed', 'unfollowed', 'checked_in', 'saved', 'follow', 'following', 'no_comments_yet', 'load_more', 'nothing_found', 'mark_all_read_done'];
    $out = [];
    foreach ($keys as $k) $out[$k] = t($k);
    return $out;
}

function page_foot($script = '') {
    ?>
<?php if (isLoggedIn()): ?>
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content pe-modal" id="reportForm">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-flag"></i> <?php echo t('report_title'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small"><?php echo t('report_intro'); ?></p>
                <input type="hidden" name="promise_id" id="reportPromiseId">
                <label class="form-label"><?php echo t('report_reason'); ?></label>
                <select name="reason" class="form-select mb-3" required>
                    <?php foreach (PE_REPORT_REASONS as $r): ?>
                        <option value="<?php echo $r; ?>"><?php echo t('reason_' . $r); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label"><?php echo t('report_details'); ?></label>
                <textarea name="details" class="form-control" rows="3" maxlength="500" placeholder="<?php echo escapeHtml(t('report_details_ph')); ?>"></textarea>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal"><?php echo t('cancel'); ?></button>
                <button type="submit" class="btn btn-brand"><i class="bi bi-flag-fill"></i> <?php echo t('report_submit'); ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<div class="toast-x" id="toast" role="status" aria-live="polite"></div>
<canvas id="confetti" aria-hidden="true"></canvas>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.PE = <?php echo json_encode([
    'csrf' => isLoggedIn() ? csrf_token() : '',
    'lang' => lang(),
    'logged' => isLoggedIn(),
    'strings' => js_strings(),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="app.js?v=<?php echo @filemtime(__DIR__ . '/app.js') ?: 1; ?>"></script>
<?php echo $script; ?>
</body>
</html>
    <?php
}

// ---------- Auth pages shell ----------
function auth_start(string $title, string $headline, array $points) {
    page_head($title, 'auth-page');
    ?>
<div class="auth-grid">
    <aside class="auth-side">
        <a class="brand" href="index.php"><span class="brand-mark"><i class="bi bi-patch-check-fill"></i></span> PromiseEdger</a>
        <div class="auth-side-mid">
            <h2 class="mb-4"><?php echo $headline; ?></h2>
            <ul class="auth-points">
                <?php foreach ($points as $pt): ?>
                    <li><i class="bi bi-check-circle-fill"></i> <?php echo escapeHtml($pt); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="small auth-copy">&copy; <?php echo date('Y'); ?> PromiseEdger</div>
    </aside>
    <main class="auth-main">
        <div class="auth-tools"><?php lang_toggle(); theme_toggle(); ?></div>
        <div class="card-x auth-card">
    <?php
}

function auth_end() {
    ?>
            <p class="text-center mt-3 mb-0"><a href="index.php" class="small text-secondary"><i class="bi <?php echo i_back(); ?>"></i> <?php echo t('back_home'); ?></a></p>
        </div>
    </main>
</div>
    <?php
    page_foot();
}
