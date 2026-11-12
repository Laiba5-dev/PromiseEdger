<?php
// File: register.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!csrf_ok()) {
        $error = t('err_invalid_request');
    } elseif ($name === '' || $email === '' || $password === '') {
        $error = t('err_all_required');
    } elseif (mb_strlen($name) > 100) {
        $error = t('err_name_long');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $error = t('err_bad_email');
    } elseif (strlen($password) < 6) {
        $error = t('err_password_short');
    } elseif ($password !== $confirm) {
        $error = t('err_password_mismatch');
    } elseif (db_val($conn, "SELECT id FROM users WHERE email = ? LIMIT 1", "s", [$email])) {
        $error = t('err_email_taken');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Pehla user (khali database) khud admin + verified ban jata hai
        $first = ((int)db_val($conn, "SELECT COUNT(*) FROM users") === 0);
        $role = $first ? 'admin' : 'user';
        $verified = $first ? date('Y-m-d H:i:s') : null;
        db_exec($conn, "INSERT INTO users (name, email, password, role, email_verified_at) VALUES (?, ?, ?, ?, ?)", "sssss", [$name, $email, $hash, $role, $verified]);
        $newId = (int)$conn->insert_id;

        if ($first) {
            $_SESSION['success'] = t('account_created_admin');
        } else {
            sendVerificationEmail($conn, ['id' => $newId, 'name' => $name, 'email' => $email]);
            $_SESSION['success'] = t('account_created_verify');
        }
        header("Location: login.php");
        exit();
    }
}

auth_start(t('create_account') . ' - PromiseEdger', t('register_headline'), [t('register_p1'), t('register_p2'), t('register_p3')]);
?>
            <h1 class="h3 fw-bold mb-4"><?php echo t('create_account'); ?></h1>

            <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo escapeHtml($error); ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                <div class="mb-3">
                    <label class="form-label"><?php echo t('full_name'); ?></label>
                    <input type="text" name="name" class="form-control" placeholder="<?php echo escapeHtml(t('your_name')); ?>" maxlength="100"
                           value="<?php echo escapeHtml($_POST['name'] ?? ''); ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo t('email'); ?></label>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" dir="ltr"
                           value="<?php echo escapeHtml($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="form-label"><?php echo t('password'); ?></label>
                        <input type="password" name="password" class="form-control" placeholder="<?php echo escapeHtml(t('min_6')); ?>" dir="ltr" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label"><?php echo t('confirm'); ?></label>
                        <input type="password" name="confirm" class="form-control" placeholder="<?php echo escapeHtml(t('repeat_password')); ?>" dir="ltr" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-brand w-100 py-2"><i class="bi bi-person-plus"></i> <?php echo t('create_account'); ?></button>
            </form>

            <p class="text-center text-secondary mt-4 mb-0">
                <?php echo t('have_account'); ?> <a href="login.php" class="fw-semibold"><?php echo t('login'); ?></a>
            </p>
<?php auth_end();
