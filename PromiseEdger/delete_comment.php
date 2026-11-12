<?php
// File: delete_comment.php
require_once __DIR__ . '/config/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    $_SESSION['error'] = t('err_invalid_request');
    header("Location: dashboard.php");
    exit();
}

// deleteComment() khud check karta hai: sirf comment ka author ya admin
deleteComment($conn, (int)($_POST['id'] ?? 0), (int)getCurrentUserId());
$_SESSION['success'] = t('comment_deleted');
redirect_back();
