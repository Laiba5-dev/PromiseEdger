<?php
// File: verify_email.php - email ke link se verify
require_once __DIR__ . '/config/config.php';

$row = findToken($conn, (string)($_GET['token'] ?? ''), 'verify');
if ($row) {
    db_exec($conn, "UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?", "i", [(int)$row['user_id']]);
    db_exec($conn, "UPDATE user_tokens SET used_at = NOW() WHERE id = ?", "i", [(int)$row['token_id']]);
    $_SESSION['success'] = t('email_verified_ok');
} else {
    $_SESSION['error'] = t('err_token_invalid');
}
header("Location: " . (isLoggedIn() ? "dashboard.php" : "login.php"));
exit();
