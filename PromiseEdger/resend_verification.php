<?php
// File: resend_verification.php
require_once __DIR__ . '/config/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    $_SESSION['error'] = t('err_invalid_request');
    redirect_back();
}
$me = getCurrentUser($conn);
if (!$me) { header("Location: login.php"); exit(); }

if (!empty($me['email_verified_at'])) {
    $_SESSION['success'] = t('already_verified');
} elseif (recentTokenExists($conn, (int)$me['id'], 'verify', 60)) {
    $_SESSION['error'] = t('err_wait_minute');
} else {
    sendVerificationEmail($conn, $me);
    $_SESSION['success'] = t('verification_sent');
}
redirect_back();
