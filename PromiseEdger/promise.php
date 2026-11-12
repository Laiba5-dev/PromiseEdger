<?php
// File: promise.php - ek promise ka poora page (share link / notification link)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$uid = (int)getCurrentUserId();
$isAdmin = isAdmin($conn);
$p = getPromiseById($conn, (int)($_GET['id'] ?? 0), $uid);
if (!$p) {
    $_SESSION['error'] = t('err_promise_not_found');
    header("Location: dashboard.php");
    exit();
}
$x = loadCardExtras($conn, [$p], $uid);

page_head($p['title'] . ' - PromiseEdger');
page_nav('dashboard');
?>
<main class="container py-4 py-lg-5" style="max-width: 760px;">
    <?php page_flash(); ?>
    <a href="dashboard.php" class="small text-secondary"><i class="bi <?php echo i_back(); ?>"></i> <?php echo t('back_dashboard'); ?></a>
    <div class="mt-3">
        <?php render_promise_card($p, $x, ['uid' => $uid, 'isAdmin' => $isAdmin, 'csrf' => csrf_token(), 'open_comments' => true]); ?>
    </div>
</main>
<?php page_foot(); ?>
