<?php
// File: follow.php - follow / unfollow (JSON)
require_once __DIR__ . '/config/config.php';
$uid = api_guard($conn);
$target = (int)($_POST['user_id'] ?? 0);

if ($target === $uid) json_out(false, ['message' => t('err_follow_self')], 400);
if (!db_val($conn, "SELECT 1 FROM users WHERE id = ?", "i", [$target])) json_out(false, ['message' => t('err_user_not_found')], 404);

if (isFollowing($conn, $uid, $target)) {
    db_exec($conn, "DELETE FROM follows WHERE follower_id = ? AND following_id = ?", "ii", [$uid, $target]);
    $now = false;
} else {
    db_exec($conn, "INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)", "ii", [$uid, $target]);
    notify($conn, $target, $uid, 'follow', null, null, 1440);
    $now = true;
}
$followers = (int)db_val($conn, "SELECT COUNT(*) FROM follows WHERE following_id = ?", "i", [$target]);
json_out(true, ['following' => $now, 'followers' => $followers]);
