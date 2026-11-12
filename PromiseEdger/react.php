<?php
// File: react.php - emoji reaction lagao / badlo / hatao (JSON)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
$uid = api_guard($conn);

$pid = (int)($_POST['promise_id'] ?? 0);
$type = (string)($_POST['type'] ?? '');
if (!isset(PE_REACTIONS[$type])) json_out(false, ['message' => t('err_invalid_request')], 400);

$p = getPromiseById($conn, $pid, $uid);
if (!$p) json_out(false, ['message' => t('err_promise_not_found')], 404);

$existing = db_val($conn, "SELECT type FROM reactions WHERE promise_id = ? AND user_id = ?", "ii", [$pid, $uid]);
if ($existing === $type) {
    db_exec($conn, "DELETE FROM reactions WHERE promise_id = ? AND user_id = ?", "ii", [$pid, $uid]);
    $mine = null;
} else {
    db_exec($conn, "INSERT INTO reactions (promise_id, user_id, type) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE type = VALUES(type), created_at = CURRENT_TIMESTAMP", "iis", [$pid, $uid, $type]);
    $mine = $type;
    // Sirf pehli baar (ya 1 ghante baad) notify karo, spam se bachne ke liye
    notify($conn, (int)$p['user_id'], $uid, 'reaction', $pid, $type, 60, $p['title']);
}

$counts = reactionCounts($conn, $pid);
json_out(true, ['mine' => $mine, 'total' => array_sum($counts), 'html' => card_react($p, $counts, $mine, true)]);
