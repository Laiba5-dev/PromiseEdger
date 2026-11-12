<?php
// File: promise_update.php - status + progress badlo (JSON). Sirf owner.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
$uid = api_guard($conn);
$pid = (int)($_POST['promise_id'] ?? 0);

$p = db_one($conn, "SELECT * FROM promises WHERE id = ?", "i", [$pid]);
if (!$p || (int)$p['user_id'] !== $uid) json_out(false, ['message' => t('err_not_allowed')], 403);

$status = (string)($_POST['status'] ?? $p['status']);
$progress = isset($_POST['progress']) ? (int)$_POST['progress'] : (int)$p['progress'];
[$status, $progress, $completed, $err] = normalizeStatus($status, $progress, $p['due_date'], $p['completed_at']);
if ($err) json_out(false, ['message' => $err], 400);

db_exec($conn, "UPDATE promises SET status = ?, progress = ?, completed_at = ? WHERE id = ?", "sisi", [$status, $progress, $completed, $pid]);

$celebrate = ($status === 'done' && $p['status'] !== 'done');
$p['status'] = $status; $p['progress'] = $progress; $p['completed_at'] = $completed;
$week = array_column(db_all($conn, "SELECT checkin_date FROM checkins WHERE promise_id = ? AND checkin_date >= CURDATE() - INTERVAL 6 DAY", "i", [$pid]), 'checkin_date');
json_out(true, [
    'status' => $status, 'progress' => $progress, 'celebrate' => $celebrate,
    'badges' => card_badges($p), 'progress_html' => card_progress($p, true), 'streak' => card_streak($p, $week, true),
]);
