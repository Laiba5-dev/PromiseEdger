<?php
// File: edit_promise.php - owner or admin can edit
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$uid = (int)getCurrentUserId();
$isAdmin = isAdmin($conn);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$promise = db_one($conn, "SELECT p.*, u.name AS author_name FROM promises p JOIN users u ON p.user_id = u.id WHERE p.id = ?", "i", [$id]);

// Sirf owner ya admin
if (!$promise || ((int)$promise['user_id'] !== $uid && !$isAdmin)) {
    $_SESSION['error'] = t('err_cant_edit');
    header("Location: dashboard.php");
    exit();
}

$byAdmin = ((int)$promise['user_id'] !== $uid);
$from = (($_GET['from'] ?? $_POST['from'] ?? '') === 'admin' && $isAdmin) ? 'admin' : '';
$backUrl = $from === 'admin' ? 'admin.php?tab=promises' : 'promise.php?id=' . $id;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
    $category = sanitizeCategory($_POST['category'] ?? 'other');
    $due = parseDueDate($_POST['due_date'] ?? '', $error);

    [$status, $progress, $completed, $statusErr] = normalizeStatus((string)($_POST['status'] ?? $promise['status']), (int)($_POST['progress'] ?? $promise['progress']), $due, $promise['completed_at']);

    if (!csrf_ok()) {
        $error = t('err_invalid_request');
    } elseif ($error === '' && ($title === '' || mb_strlen($title) > 255)) {
        $error = t('err_title_required');
    } elseif ($error === '' && $statusErr) {
        $error = $statusErr;
    }

    if ($error === '') {
        // reminder_sent_on ko pehle likhna zaroori hai (UPDATE left-to-right chalta hai, purani due_date se compare karna hai)
        db_exec($conn, "UPDATE promises SET reminder_sent_on = IF(due_date <=> ?, reminder_sent_on, NULL),
                        title = ?, description = ?, visibility = ?, category = ?, due_date = ?, status = ?, progress = ?, completed_at = ? WHERE id = ?",
            "sssssssisi", [$due, $title, $description, $visibility, $category, $due, $status, $progress, $completed, $id]);
        $_SESSION['success'] = t('promise_updated');
        header("Location: " . $backUrl);
        exit();
    }
    $promise = array_merge($promise, ['title' => $title, 'description' => $description, 'visibility' => $visibility, 'category' => $category,
        'due_date' => $due, 'status' => $_POST['status'] ?? $promise['status'], 'progress' => (int)($_POST['progress'] ?? 0)]);
}

page_head(t('edit_promise') . ' - PromiseEdger');
page_nav($from === 'admin' ? 'admin' : 'dashboard');
?>
<main class="container py-4 py-lg-5" style="max-width: 760px;">
    <a href="<?php echo $backUrl; ?>" class="small text-secondary"><i class="bi <?php echo i_back(); ?>"></i> <?php echo t('back'); ?></a>
    <div class="d-flex align-items-center gap-3 mt-2 mb-4 anim-up">
        <h1 class="h2 page-title mb-0"><?php echo t('edit_promise'); ?></h1>
        <?php if ($byAdmin): ?>
            <span class="pill pill-admin"><i class="bi bi-shield-check"></i> <?php echo t('admin'); ?> &middot; <?php echo escapeHtml($promise['author_name']); ?></span>
        <?php endif; ?>
    </div>

    <div class="card-x p-4 p-md-5 anim-up" style="--i:1">
        <?php if ($error): ?><div class="alert alert-danger"><?php echo escapeHtml($error); ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$promise['id']; ?>">
            <input type="hidden" name="from" value="<?php echo $from; ?>">
            <?php promise_form_fields($promise, true); ?>
            <div class="d-flex gap-2">
                <button class="btn btn-brand"><i class="bi bi-check2"></i> <?php echo t('save'); ?></button>
                <a href="<?php echo $backUrl; ?>" class="btn btn-ghost"><?php echo t('cancel'); ?></a>
            </div>
        </form>
    </div>
</main>
<?php page_foot(); ?>
