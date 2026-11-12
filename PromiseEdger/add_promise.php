<?php
// File: add_promise.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$error = '';
$verified = isVerified($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
    $category = sanitizeCategory($_POST['category'] ?? 'other');
    $due = parseDueDate($_POST['due_date'] ?? '', $error);

    if (!csrf_ok()) {
        $error = t('err_invalid_request');
    } elseif (!$verified) {
        $error = t('err_verify_first');
    } elseif ($error === '' && ($title === '' || mb_strlen($title) > 255)) {
        $error = t('err_title_required');
    } elseif ($error === '' && $due !== null && $due < today()) {
        $error = t('err_deadline_past');
    }
    if ($error === '') {
        $uid = (int)getCurrentUserId();
        db_exec($conn, "INSERT INTO promises (user_id, title, description, visibility, category, due_date) VALUES (?, ?, ?, ?, ?, ?)",
            "isssss", [$uid, $title, $description, $visibility, $category, $due]);
        $_SESSION['success'] = t('promise_created');
        header("Location: dashboard.php");
        exit();
    }
}

page_head(t('new_promise') . ' - PromiseEdger');
page_nav('add');
?>
<main class="container py-4 py-lg-5" style="max-width: 760px;">
    <a href="dashboard.php" class="small text-secondary"><i class="bi <?php echo i_back(); ?>"></i> <?php echo t('back_dashboard'); ?></a>
    <h1 class="h2 page-title mt-2 mb-4 anim-up"><?php echo t('new_promise'); ?></h1>

    <div class="card-x p-4 p-md-5 anim-up" style="--i:1">
        <?php page_flash(); ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo escapeHtml($error); ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <?php promise_form_fields($_POST ?: ['category' => 'other', 'visibility' => 'public'], false); ?>
            <div class="d-flex gap-2">
                <button class="btn btn-brand"><i class="bi bi-check2"></i> <?php echo t('save'); ?></button>
                <a href="dashboard.php" class="btn btn-ghost"><?php echo t('cancel'); ?></a>
            </div>
        </form>
    </div>
</main>
<?php page_foot(); ?>
