<?php
// File: notifications.php - list page + small JSON/redirect helpers
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();
$uid = (int)getCurrentUserId();

// Badge ke liye polling
if (isset($_GET['count'])) {
    json_out(true, ['unread' => unreadCount($conn, $uid)]);
}

// Notification kholo -> read mark karo -> target par bhejo
if (isset($_GET['go'])) {
    $n = db_one($conn, "SELECT * FROM notifications WHERE id = ? AND user_id = ?", "ii", [(int)$_GET['go'], $uid]);
    if ($n) {
        db_exec($conn, "UPDATE notifications SET is_read = 1 WHERE id = ?", "i", [(int)$n['id']]);
        header("Location: " . notif_url($n));
        exit();
    }
    header("Location: notifications.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) json_out(false, ['message' => t('err_invalid_request')], 403);
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all') {
        db_exec($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", "i", [$uid]);
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') json_out(true, ['unread' => 0]);
        $_SESSION['success'] = t('mark_all_read_done');
    } elseif ($action === 'clear') {
        db_exec($conn, "DELETE FROM notifications WHERE user_id = ?", "i", [$uid]);
        $_SESSION['success'] = t('notifications_cleared');
    }
    header("Location: notifications.php");
    exit();
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$total = (int)db_val($conn, "SELECT COUNT(*) FROM notifications WHERE user_id = ?", "i", [$uid]);
$items = db_all($conn, "SELECT n.*, a.name AS actor_name FROM notifications n LEFT JOIN users a ON a.id = n.actor_id
    WHERE n.user_id = ? ORDER BY n.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), "i", [$uid]);
$pages = max(1, (int)ceil($total / $per));
$unread = unreadCount($conn, $uid);

page_head(t('notifications') . ' - PromiseEdger');
page_nav('');
?>
<main class="container py-4 py-lg-5" style="max-width: 800px;">
    <?php page_flash(); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h2 page-title mb-0 anim-up"><i class="bi bi-bell"></i> <?php echo t('notifications'); ?></h1>
        <div class="d-flex gap-2">
            <?php if ($unread): ?>
            <form method="POST"><input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>"><input type="hidden" name="action" value="mark_all">
                <button class="btn btn-ghost btn-sm"><i class="bi bi-check2-all"></i> <?php echo t('mark_all_read'); ?></button></form>
            <?php endif; ?>
            <?php if ($total): ?>
            <form method="POST" data-confirm="<?php echo escapeHtml(t('confirm_clear_notifications')); ?>"><input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>"><input type="hidden" name="action" value="clear">
                <button class="btn btn-ghost btn-sm"><i class="bi bi-trash"></i> <?php echo t('clear_all'); ?></button></form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$items): ?>
        <div class="card-x empty anim-up"><i class="bi bi-bell-slash"></i><h5 class="mt-3"><?php echo t('no_notifications'); ?></h5>
            <p class="text-secondary mb-0"><?php echo t('no_notifications_hint'); ?></p></div>
    <?php else: ?>
        <div class="card-x notif-page anim-up">
            <?php foreach ($items as $n) echo notif_item_html($n); ?>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="d-flex justify-content-center gap-2 mt-4">
                <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="?page=<?php echo $page - 1; ?>"><i class="bi <?php echo i_back(); ?>"></i></a><?php endif; ?>
                <span class="btn btn-ghost btn-sm disabled"><?php echo $page; ?> / <?php echo $pages; ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="?page=<?php echo $page + 1; ?>"><i class="bi <?php echo i_next(); ?>"></i></a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php page_foot(); ?>
