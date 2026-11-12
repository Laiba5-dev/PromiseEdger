<?php
// File: delete_promise.php
require_once __DIR__ . '/config/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    $_SESSION['error'] = t('err_invalid_request');
    header("Location: dashboard.php");
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$row = db_one($conn, "SELECT user_id FROM promises WHERE id = ?", "i", [$id]);

if (!$row) {
    $_SESSION['error'] = t('err_promise_not_found');
} elseif ((int)$row['user_id'] !== (int)getCurrentUserId() && !isAdmin($conn)) {
    $_SESSION['error'] = t('err_not_allowed');
} else {
    // reactions, comments, check-ins, reports sab FOREIGN KEY CASCADE se khud delete hote hain
    db_exec($conn, "DELETE FROM promises WHERE id = ?", "i", [$id]);
    $_SESSION['success'] = t('promise_deleted');
}

// Agar isi promise ke page par thay to dashboard par bhejo
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($ref, 'promise.php') !== false) { header("Location: dashboard.php"); exit(); }
redirect_back();
