<?php
// File: admin.php - Admin panel (analytics, users, promises, reports)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();
requireAdmin($conn);

$csrf = csrf_token();
$me = (int)getCurrentUserId();
$tab = $_GET['tab'] ?? 'analytics';
if (!in_array($tab, ['analytics', 'users', 'promises', 'reports'], true)) $tab = 'analytics';

// ========== ACTIONS (POST) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $_SESSION['error'] = t('err_invalid_request');
        header("Location: admin.php");
        exit();
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'toggle_role') {
        if ($id === $me) $_SESSION['error'] = t('err_own_role');
        elseif (toggleAdminStatus($conn, $id)) $_SESSION['success'] = t('role_updated');
        else $_SESSION['error'] = t('err_role_failed');
        header("Location: admin.php?tab=users"); exit();
    }

    if ($action === 'verify_user') {
        db_exec($conn, "UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?", "i", [$id]);
        $_SESSION['success'] = t('user_verified');
        header("Location: admin.php?tab=users"); exit();
    }

    if ($action === 'delete_user') {
        if ($id === $me) {
            $_SESSION['error'] = t('err_own_delete');
        } else {
            $pic = db_val($conn, "SELECT profile_picture FROM users WHERE id = ?", "i", [$id]);
            db_exec($conn, "DELETE FROM users WHERE id = ?", "i", [$id]);   // baaki sab CASCADE se
            if ($pic) { $f = __DIR__ . '/uploads/avatars/' . basename($pic); if (is_file($f)) @unlink($f); }
            $_SESSION['success'] = t('user_deleted');
        }
        header("Location: admin.php?tab=users"); exit();
    }

    if ($action === 'delete_promise') {
        db_exec($conn, "DELETE FROM promises WHERE id = ?", "i", [$id]);
        $_SESSION['success'] = t('promise_deleted');
        header("Location: admin.php?tab=promises"); exit();
    }

    // ---- Reports ----
    if ($action === 'report_dismiss') {
        $r = db_one($conn, "SELECT r.*, p.title FROM reports r JOIN promises p ON p.id = r.promise_id WHERE r.id = ?", "i", [$id]);
        if ($r && $r['status'] === 'pending') {
            db_exec($conn, "UPDATE reports SET status = 'dismissed', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", "ii", [$me, $id]);
            notify($conn, (int)$r['reporter_id'], null, 'report_resolved', (int)$r['promise_id'], 'dismissed', 0, $r['title']);
            $_SESSION['success'] = t('report_dismissed');
        }
        header("Location: admin.php?tab=reports"); exit();
    }

    if ($action === 'report_remove') {   // promise hatao + us par saari reports resolve
        $r = db_one($conn, "SELECT r.promise_id, p.title, p.user_id FROM reports r JOIN promises p ON p.id = r.promise_id WHERE r.id = ?", "i", [$id]);
        if ($r) {
            $pid = (int)$r['promise_id'];
            foreach (db_all($conn, "SELECT DISTINCT reporter_id FROM reports WHERE promise_id = ? AND status = 'pending'", "i", [$pid]) as $rep) {
                notify($conn, (int)$rep['reporter_id'], null, 'report_resolved', null, 'resolved', 0, $r['title']);
            }
            notify($conn, (int)$r['user_id'], null, 'removed', null, null, 0, $r['title']);
            db_exec($conn, "DELETE FROM promises WHERE id = ?", "i", [$pid]);
            $_SESSION['success'] = t('report_removed');
        }
        header("Location: admin.php?tab=reports"); exit();
    }
}

// ========== DATA ==========
$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM users) AS users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admins,
    (SELECT COUNT(*) FROM promises) AS promises,
    (SELECT COUNT(*) FROM promises WHERE status = 'done') AS done,
    (SELECT COUNT(*) FROM reactions) AS reactions,
    (SELECT COUNT(*) FROM comments) AS comments,
    (SELECT COUNT(*) FROM reports WHERE status = 'pending') AS pending_reports")->fetch_assoc();

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;

function dailySeries(mysqli $c, string $table, string $col, int $days, string $extra = ''): array {
    $map = [];
    foreach (db_all($c, "SELECT DATE($col) AS d, COUNT(*) AS n FROM $table WHERE $col >= CURDATE() - INTERVAL " . ($days - 1) . " DAY $extra GROUP BY DATE($col)") as $r) $map[$r['d']] = (int)$r['n'];
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $out[$d] = $map[$d] ?? 0; }
    return $out;
}

if ($tab === 'analytics') {
    $newUsers = dailySeries($conn, 'users', 'created_at', $days);
    $promisesDay = dailySeries($conn, 'promises', 'created_at', $days);
    $doneDay = dailySeries($conn, 'promises', 'completed_at', $days, "AND status = 'done'");
    $reactDay = dailySeries($conn, 'reactions', 'created_at', $days);
    $commentDay = dailySeries($conn, 'comments', 'created_at', $days);
    $running = (int)db_val($conn, "SELECT COUNT(*) FROM users WHERE created_at < CURDATE() - INTERVAL " . ($days - 1) . " DAY");
    $totalUsers = [];
    foreach ($newUsers as $d => $n) { $running += $n; $totalUsers[] = $running; }

    $labels = array_map(fn($d) => fmtDate($d, false), array_keys($newUsers));
    $statusDist = ['in_progress' => 0, 'done' => 0, 'missed' => 0];
    foreach ($conn->query("SELECT status, COUNT(*) n FROM promises GROUP BY status") as $r) $statusDist[$r['status']] = (int)$r['n'];
    $catDist = array_fill_keys(array_keys(PE_CATEGORIES), 0);
    foreach ($conn->query("SELECT category, COUNT(*) n FROM promises GROUP BY category") as $r) if (isset($catDist[$r['category']])) $catDist[$r['category']] = (int)$r['n'];

    $periodNew = array_sum($newUsers); $periodPromises = array_sum($promisesDay); $periodDone = array_sum($doneDay);
    $prevUsers = (int)db_val($conn, "SELECT COUNT(*) FROM users WHERE created_at >= CURDATE() - INTERVAL " . (2 * $days - 1) . " DAY AND created_at < CURDATE() - INTERVAL " . ($days - 1) . " DAY");
    $prevPromises = (int)db_val($conn, "SELECT COUNT(*) FROM promises WHERE created_at >= CURDATE() - INTERVAL " . (2 * $days - 1) . " DAY AND created_at < CURDATE() - INTERVAL " . ($days - 1) . " DAY");
    $rate = $stats['promises'] ? round($stats['done'] / $stats['promises'] * 100) : 0;
    $delta = fn($now, $prev) => $prev > 0 ? round(($now - $prev) / $prev * 100) : ($now > 0 ? 100 : 0);
}
if ($tab === 'users') $users = getAllUsers($conn);
if ($tab === 'promises') {
    $promises = $conn->query("SELECT p.*, u.name AS author_name,
        (SELECT COUNT(*) FROM reactions WHERE promise_id = p.id) AS reaction_count,
        (SELECT COUNT(*) FROM comments WHERE promise_id = p.id) AS comment_count
        FROM promises p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
}
if ($tab === 'reports') {
    $base = "SELECT r.*, p.title, p.description, p.user_id AS owner_id, o.name AS owner_name, rp.name AS reporter_name, rv.name AS reviewer_name,
        (SELECT COUNT(*) FROM reports x WHERE x.promise_id = r.promise_id) AS report_count
        FROM reports r JOIN promises p ON p.id = r.promise_id JOIN users o ON o.id = p.user_id JOIN users rp ON rp.id = r.reporter_id
        LEFT JOIN users rv ON rv.id = r.reviewed_by";
    $pending = $conn->query("$base WHERE r.status = 'pending' ORDER BY r.created_at ASC")->fetch_all(MYSQLI_ASSOC);
    $reviewed = $conn->query("$base WHERE r.status <> 'pending' ORDER BY r.reviewed_at DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
}

page_head(t('nav_admin') . ' - PromiseEdger');
page_nav('admin');
$tabLink = fn($k, $icon, $label, $badge = 0) => '<li class="nav-item"><a class="nav-link ' . ($tab === $k ? 'active' : '') . '" href="admin.php?tab=' . $k . '"><i class="bi ' . $icon . '"></i> '
    . $label . ($badge ? ' <span class="count-pill">' . $badge . '</span>' : '') . '</a></li>';
?>
<main class="container py-4 py-lg-5">
    <?php page_flash(); ?>
    <h1 class="h2 page-title mb-4 anim-up"><i class="bi bi-shield-lock text-gold"></i> <?php echo t('nav_admin'); ?></h1>

    <ul class="nav nav-pills pills-x mb-4 anim-up" style="--i:1">
        <?php echo $tabLink('analytics', 'bi-graph-up-arrow', t('analytics'));
              echo $tabLink('users', 'bi-people', t('users'));
              echo $tabLink('promises', 'bi-bookmark-heart', t('promises'));
              echo $tabLink('reports', 'bi-flag', t('reports'), (int)$stats['pending_reports']); ?>
    </ul>

<?php if ($tab === 'analytics'): ?>
    <div class="d-flex justify-content-end mb-3">
        <div class="tabs-x">
            <?php foreach ([7, 30, 90] as $d): ?><a class="<?php echo $days === $d ? 'active' : ''; ?>" href="admin.php?tab=analytics&days=<?php echo $d; ?>"><?php echo t('last_n_days', $d); ?></a><?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $kpis = [
            [t('users'), $stats['users'], 'bi-people', '+' . $periodNew . ' ' . t('this_period'), $delta($periodNew, $prevUsers)],
            [t('promises'), $stats['promises'], 'bi-bookmark-heart', '+' . $periodPromises . ' ' . t('this_period'), $delta($periodPromises, $prevPromises)],
            [t('completion_rate'), $rate . '%', 'bi-check2-circle', $periodDone . ' ' . t('completed_period'), null],
            [t('reactions'), $stats['reactions'], 'bi-emoji-smile', $stats['comments'] . ' ' . t('comments'), null],
            [t('pending_reports'), $stats['pending_reports'], 'bi-flag', t('needs_review'), null],
        ];
        foreach ($kpis as $i => $k): ?>
            <div class="col-6 col-lg"><div class="card-x kpi anim-up" style="--i:<?php echo $i; ?>">
                <div class="kpi-ico"><i class="bi <?php echo $k[2]; ?>"></i></div>
                <div class="kpi-num"><?php echo is_numeric($k[1]) ? '<span data-count="' . (int)$k[1] . '">0</span>' : escapeHtml($k[1]); ?></div>
                <div class="kpi-label"><?php echo $k[0]; ?></div>
                <div class="kpi-sub"><?php echo escapeHtml($k[3]); ?>
                    <?php if ($k[4] !== null): ?><span class="delta <?php echo $k[4] >= 0 ? 'up' : 'down'; ?>"><i class="bi bi-caret-<?php echo $k[4] >= 0 ? 'up' : 'down'; ?>-fill"></i><?php echo abs($k[4]); ?>%</span><?php endif; ?></div>
            </div></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-7"><div class="card-x chart-card"><h6><?php echo t('chart_growth'); ?></h6><div class="chart-box"><canvas id="chGrowth"></canvas></div></div></div>
        <div class="col-lg-5"><div class="card-x chart-card"><h6><?php echo t('chart_status'); ?></h6><div class="chart-box"><canvas id="chStatus"></canvas></div></div></div>
        <div class="col-lg-7"><div class="card-x chart-card"><h6><?php echo t('chart_daily_promises'); ?></h6><div class="chart-box"><canvas id="chDaily"></canvas></div></div></div>
        <div class="col-lg-5"><div class="card-x chart-card"><h6><?php echo t('chart_categories'); ?></h6><div class="chart-box"><canvas id="chCats"></canvas></div></div></div>
        <div class="col-12"><div class="card-x chart-card"><h6><?php echo t('chart_engagement'); ?></h6><div class="chart-box"><canvas id="chEng"></canvas></div></div></div>
    </div>

<?php elseif ($tab === 'users'): ?>
    <div class="card-x p-3 table-responsive anim-up">
        <table class="table align-middle mb-0">
            <thead><tr><th><?php echo t('user'); ?></th><th><?php echo t('email'); ?></th><th><?php echo t('role'); ?></th><th><?php echo t('promises'); ?></th><th><?php echo t('st_done'); ?></th><th><?php echo t('joined'); ?></th><th class="text-end"><?php echo t('actions'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = ((int)$u['id'] === $me); ?>
                <tr>
                    <td><a href="profile.php?id=<?php echo (int)$u['id']; ?>"><?php echo escapeHtml($u['name']); ?></a> <?php if ($self): ?><span class="pill pill-private"><?php echo t('you'); ?></span><?php endif; ?></td>
                    <td class="text-secondary"><?php echo escapeHtml($u['email']); ?>
                        <?php if (empty($u['email_verified_at'])): ?><span class="pill pill-private" title="<?php echo escapeHtml(t('unverified')); ?>"><i class="bi bi-envelope-exclamation"></i></span><?php endif; ?></td>
                    <td><span class="pill <?php echo ($u['role'] ?? 'user') === 'admin' ? 'pill-admin' : 'pill-public'; ?>"><?php echo escapeHtml($u['role'] ?? 'user'); ?></span></td>
                    <td><?php echo (int)$u['promise_count']; ?></td>
                    <td><?php echo (int)$u['done_count']; ?></td>
                    <td class="text-secondary"><?php echo escapeHtml(fmtDate($u['created_at'])); ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (empty($u['email_verified_at'])): ?>
                        <form method="POST" class="d-inline"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="verify_user"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <button class="btn btn-sm btn-ghost" title="<?php echo escapeHtml(t('mark_verified')); ?>"><i class="bi bi-patch-check"></i></button></form>
                        <?php endif; ?>
                        <?php if (!$self): ?>
                        <form method="POST" class="d-inline"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="toggle_role"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <button class="btn btn-sm btn-ghost"><?php echo ($u['role'] ?? 'user') === 'admin' ? t('remove_admin') : t('make_admin'); ?></button></form>
                        <form method="POST" class="d-inline" data-confirm="<?php echo escapeHtml(t('confirm_delete_user')); ?>"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                            <button class="btn btn-sm btn-danger-soft"><i class="bi bi-trash"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($tab === 'promises'): ?>
    <div class="card-x p-3 table-responsive anim-up">
        <table class="table align-middle mb-0">
            <thead><tr><th><?php echo t('title'); ?></th><th><?php echo t('author'); ?></th><th><?php echo t('category'); ?></th><th><?php echo t('status'); ?></th><th><?php echo t('visibility'); ?></th><th><i class="bi bi-emoji-smile"></i></th><th><i class="bi bi-chat"></i></th><th class="text-end"><?php echo t('actions'); ?></th></tr></thead>
            <tbody>
            <?php if (empty($promises)): ?><tr><td colspan="8" class="text-secondary"><?php echo t('no_promises_yet'); ?></td></tr><?php endif; ?>
            <?php foreach ($promises as $p): ?>
                <tr>
                    <td><a href="promise.php?id=<?php echo (int)$p['id']; ?>"><?php echo escapeHtml($p['title']); ?></a></td>
                    <td><a href="profile.php?id=<?php echo (int)$p['user_id']; ?>"><?php echo escapeHtml($p['author_name']); ?></a></td>
                    <td><?php echo t('cat_' . $p['category']); ?></td>
                    <td><span class="badge-x st-<?php echo $p['status']; ?>"><?php echo t('st_' . $p['status']); ?> <?php echo (int)$p['progress']; ?>%</span></td>
                    <td><span class="pill <?php echo $p['visibility'] === 'private' ? 'pill-private' : 'pill-public'; ?>"><?php echo t($p['visibility']); ?></span></td>
                    <td><?php echo (int)$p['reaction_count']; ?></td><td><?php echo (int)$p['comment_count']; ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-ghost" href="edit_promise.php?id=<?php echo (int)$p['id']; ?>&from=admin"><i class="bi bi-pencil"></i></a>
                        <form method="POST" class="d-inline" data-confirm="<?php echo escapeHtml(t('confirm_delete_promise')); ?>"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="delete_promise"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                            <button class="btn btn-sm btn-danger-soft"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <h5 class="mb-3"><?php echo t('pending_reports'); ?> <span class="count-pill"><?php echo count($pending); ?></span></h5>
    <?php if (!$pending): ?><div class="card-x empty mb-4"><i class="bi bi-shield-check"></i><h5 class="mt-3"><?php echo t('no_pending_reports'); ?></h5></div><?php endif; ?>
    <div class="d-flex flex-column gap-3 mb-5">
        <?php foreach ($pending as $i => $r): ?>
            <div class="card-x report-card anim-up" style="--i:<?php echo min($i, 6); ?>">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <span class="badge-x due-bad"><i class="bi bi-flag-fill"></i> <?php echo t('reason_' . $r['reason']); ?></span>
                    <span class="small text-secondary"><?php echo t('reported_by', escapeHtml($r['reporter_name'])); ?> &middot; <?php echo escapeHtml(formatDate($r['created_at'])); ?>
                        <?php if ((int)$r['report_count'] > 1): ?> &middot; <b><?php echo t('n_reports', (int)$r['report_count']); ?></b><?php endif; ?></span>
                </div>
                <h6 class="mb-1"><a href="promise.php?id=<?php echo (int)$r['promise_id']; ?>"><?php echo escapeHtml($r['title']); ?></a>
                    <small class="text-secondary"> &mdash; <a href="profile.php?id=<?php echo (int)$r['owner_id']; ?>"><?php echo escapeHtml($r['owner_name']); ?></a></small></h6>
                <?php if ($r['description']): ?><p class="small text-secondary mb-2 clamp-3"><?php echo escapeHtml($r['description']); ?></p><?php endif; ?>
                <?php if ($r['details']): ?><div class="report-note"><i class="bi bi-chat-quote"></i> <?php echo escapeHtml($r['details']); ?></div><?php endif; ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <form method="POST"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="report_dismiss"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <button class="btn btn-ghost btn-sm"><i class="bi bi-check2"></i> <?php echo t('dismiss_report'); ?></button></form>
                    <a class="btn btn-ghost btn-sm" href="edit_promise.php?id=<?php echo (int)$r['promise_id']; ?>&from=admin"><i class="bi bi-pencil"></i> <?php echo t('edit'); ?></a>
                    <form method="POST" data-confirm="<?php echo escapeHtml(t('confirm_remove_reported')); ?>"><input type="hidden" name="csrf" value="<?php echo $csrf; ?>"><input type="hidden" name="action" value="report_remove"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <button class="btn btn-danger-soft btn-sm"><i class="bi bi-trash"></i> <?php echo t('remove_promise'); ?></button></form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($reviewed): ?>
    <h6 class="text-secondary mb-3"><?php echo t('recently_reviewed'); ?></h6>
    <div class="card-x p-3 table-responsive"><table class="table align-middle mb-0"><tbody>
        <?php foreach ($reviewed as $r): ?>
            <tr><td><?php echo escapeHtml($r['title']); ?></td><td class="text-secondary"><?php echo t('reason_' . $r['reason']); ?></td>
                <td><span class="pill <?php echo $r['status'] === 'resolved' ? 'pill-private' : 'pill-public'; ?>"><?php echo $r['status'] === 'resolved' ? t('report_resolved') : t('report_dismissed_label'); ?></span></td>
                <td class="text-secondary small"><?php echo escapeHtml($r['reviewer_name'] ?? '-'); ?> &middot; <?php echo escapeHtml(formatDate($r['reviewed_at'])); ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
<?php endif; ?>
</main>

<?php
$js = '';
if ($tab === 'analytics') {
    ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.js"></script>
<script>
window.PE_CHARTS = <?php echo json_encode([
    'labels' => $labels,
    'users_total' => $totalUsers, 'users_new' => array_values($newUsers),
    'promises' => array_values($promisesDay), 'done' => array_values($doneDay),
    'reactions' => array_values($reactDay), 'comments' => array_values($commentDay),
    'status' => ['labels' => [t('st_in_progress'), t('st_done'), t('st_missed')], 'data' => array_values($statusDist)],
    'cats' => ['labels' => array_map(fn($k) => t('cat_' . $k), array_keys($catDist)), 'data' => array_values($catDist)],
    'text' => ['users' => t('users'), 'new_users' => t('new_users'), 'created' => t('created'), 'completed' => t('completed'), 'reactions' => t('reactions'), 'comments' => t('comments'), 'promises' => t('promises')],
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="charts.js?v=<?php echo @filemtime(__DIR__ . '/charts.js') ?: 1; ?>"></script>
<?php $js = ob_get_clean();
}
page_foot($js); ?>
