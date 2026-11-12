<?php
// File: reset_password.php - email wale link se naya password
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$row = findToken($conn, $token, 'reset');
$error = '';

if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (!csrf_ok()) {
        $error = t('err_invalid_request');
    } elseif (strlen($new) < 6) {
        $error = t('err_password_short');
    } elseif ($new !== $confirm) {
        $error = t('err_password_mismatch');
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        db_exec($conn, "UPDATE users SET password = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?", "si", [$hash, (int)$row['user_id']]);
        db_exec($conn, "UPDATE user_tokens SET used_at = NOW() WHERE id = ?", "i", [(int)$row['token_id']]);
        db_exec($conn, "DELETE FROM user_tokens WHERE user_id = ? AND type = 'reset'", "i", [(int)$row['user_id']]);
        $_SESSION['success'] = t('password_reset_done');
        header("Location: login.php");
        exit();
    }
}

auth_start(t('reset_password_title') . ' - PromiseEdger', t('forgot_headline'), [t('forgot_p1'), t('forgot_p2'), t('forgot_p3')]);
?>
            <h1 class="h3 fw-bold mb-1"><?php echo t('reset_password_title'); ?></h1>
            <?php if (!$row): ?>
                <div class="alert alert-danger mt-3"><i class="bi bi-exclamation-triangle"></i> <?php echo t('err_token_invalid'); ?></div>
                <a href="forgot_password.php" class="btn btn-brand w-100"><i class="bi bi-envelope"></i> <?php echo t('request_new_link'); ?></a>
            <?php else: ?>
                <p class="text-secondary mb-4"><?php echo t('reset_sub', escapeHtml($row['email'])); ?></p>
                <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo escapeHtml($error); ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="token" value="<?php echo escapeHtml($token); ?>">
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('new_password'); ?></label>
                        <input type="password" name="password" class="form-control" minlength="6" dir="ltr" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><?php echo t('confirm_new_password'); ?></label>
                        <input type="password" name="confirm" class="form-control" minlength="6" dir="ltr" required>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 py-2"><i class="bi bi-key"></i> <?php echo t('reset_password_btn'); ?></button>
                </form>
            <?php endif; ?>
<?php auth_end();
