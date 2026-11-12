<?php
// File: comment.php - comment likho (AJAX ya normal form)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$fail = function (string $msg, int $code = 400) use ($ajax) {
    if ($ajax) json_out(false, ['message' => $msg], $code);
    $_SESSION['error'] = $msg;
    redirect_back();
};

if (!isLoggedIn()) $fail(t('err_login_first'), 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) $fail(t('err_invalid_request'), 403);
if (!isVerified($conn)) $fail(t('err_verify_first'), 403);

$promise_id = (int)($_POST['promise_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$uid = (int)getCurrentUserId();

if ($comment === '' || mb_strlen($comment) > 1000) $fail(t('err_comment_length'));
$p = getPromiseById($conn, $promise_id, $uid);
if (!$p) $fail(t('err_promise_not_found'), 404);

$cid = addComment($conn, $promise_id, $uid, $comment);
notify($conn, (int)$p['user_id'], $uid, 'comment', $promise_id, null, 0, $p['title']);

$me = getCurrentUser($conn);
if ($ajax) {
    $row = ['id' => $cid, 'user_id' => $uid, 'author_name' => $me['name'], 'author_pic' => $me['profile_picture'], 'comment' => $comment, 'created_at' => date('Y-m-d H:i:s')];
    $count = (int)db_val($conn, "SELECT COUNT(*) FROM comments WHERE promise_id = ?", "i", [$promise_id]);
    json_out(true, ['html' => render_comment($row, ['uid' => $uid, 'isAdmin' => isAdmin($conn), 'csrf' => csrf_token(), 'readonly' => false]), 'count' => $count]);
}
$_SESSION['success'] = t('comment_added');
header("Location: promise.php?id=" . $promise_id);
exit();
