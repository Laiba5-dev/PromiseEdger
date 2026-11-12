<?php
// File: forgot_password.php - reset link email se bhejo
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';

if (isLoggedIn()) { header("Location: dashboard.php"); exit(); }

$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!csrf_ok()) {
        $error = t('err_invalid_request');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('err_bad_email');
    } else {
        $user = db_one($conn, "SELECT id, name, email FROM users WHERE email = ? LIMIT 1", "s", [$email]);
        // Chahe email ho ya na ho, jawab ek jaisa (account enumeration se bachao)
        if ($user && !recentTokenExists($conn, (int)$user['id'], 'reset', 60)) {
            sendResetEmail($conn, $user);
        }
        $sent = true;
    }
}

auth_start(t('forgot_password_title') . ' - PromiseEdger', t('forgot_headline'), [t('forgot_p1'), t('forgot_p2'), t('forgot_p3')]);
?>
            <h1 class="h3 fw-bold mb-1"><?php echo t('forgot_password_title'); ?></h1>
            <p class="text-secondary mb-4"><?php echo t('forgot_sub'); ?></p>

            <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo escapeHtml($error); ?></div><?php endif; ?>
            <?php if ($sent): ?>
                <div class="alert alert-success"><i class="bi bi-envelope-check"></i> <?php echo t('reset_link_sent'); ?></div>
                <?php page_flash(); ?>
                <a href="login.php" class="btn btn-ghost w-100"><i class="bi <?php echo i_back(); ?>"></i> <?php echo t('back_to_login'); ?></a>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                <div class="mb-4">
                    <label class="form-label"><?php echo t('email'); ?></label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" dir="ltr" required autofocus
                           value="<?php echo escapeHtml($_POST['email'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-brand w-100 py-2"><i class="bi bi-send"></i> <?php echo t('send_reset_link'); ?></button>
            </form>
            <p class="text-center mt-4 mb-0"><a href="login.php" class="small"><i class="bi <?php echo i_back(); ?>"></i> <?php echo t('back_to_login'); ?></a></p>
            <?php endif; ?>
<?php auth_end();
