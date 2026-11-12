<?php
// File: login.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fails = $_SESSION['login_fails'] ?? ['n' => 0, 't' => 0];
    if (time() - $fails['t'] > 600) $fails = ['n' => 0, 't' => time()];

    if (!csrf_ok()) {
        $error = t('err_invalid_request');
    } elseif ($fails['n'] >= 6) {
        $error = t('err_too_many_attempts');
    } elseif ($email === '' || $password === '') {
        $error = t('err_email_password_required');
    } else {
        $user = db_one($conn, "SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1", "s", [$email]);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_fails']);
            $_SESSION['user'] = [
                'id'    => (int)$user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'] ?? 'user'
            ];
            $_SESSION['user_id'] = (int)$user['id'];
            header("Location: dashboard.php");
            exit();
        }
        $fails['n']++; $fails['t'] = time();
        $_SESSION['login_fails'] = $fails;
        $error = t('err_invalid_login');
    }
}

auth_start(t('login') . ' - PromiseEdger', t('login_headline'), [t('login_p1'), t('login_p2'), t('login_p3')]);
?>
            <h1 class="h3 fw-bold mb-1"><?php echo t('login'); ?></h1>
            <p class="text-secondary mb-4"><?php echo t('login_sub'); ?></p>

            <?php page_flash(); ?>
            <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo escapeHtml($error); ?></div><?php endif; ?>

            <form method="POST" autocomplete="on">
                <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                <div class="mb-3">
                    <label class="form-label"><?php echo t('email'); ?></label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" dir="ltr"
                           value="<?php echo escapeHtml($_POST['email'] ?? ''); ?>" required autofocus>
                </div>
                <div class="mb-2">
                    <label class="form-label"><?php echo t('password'); ?></label>
                    <input type="password" name="password" class="form-control" placeholder="<?php echo escapeHtml(t('your_password')); ?>" dir="ltr" required>
                </div>
                <div class="mb-4 text-end"><a href="forgot_password.php" class="small"><?php echo t('forgot_password'); ?></a></div>
                <button type="submit" class="btn btn-brand w-100 py-2"><i class="bi bi-box-arrow-in-right"></i> <?php echo t('login'); ?></button>
            </form>

            <p class="text-center text-secondary mt-4 mb-0">
                <?php echo t('new_here'); ?> <a href="register.php" class="fw-semibold"><?php echo t('create_account'); ?></a>
            </p>
<?php auth_end();
