<?php
// File: checkin.php - roz ka check-in (streak)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/layout.php';
$uid = api_guard($conn);
$pid = (int)($_POST['promise_id'] ?? 0);

$conn->begin_transaction();
try {
    $p = db_one($conn, "SELECT * FROM promises WHERE id = ? FOR UPDATE", "i", [$pid]);
    if (!$p || (int)$p['user_id'] !== $uid) { $conn->rollback(); json_out(false, ['message' => t('err_not_allowed')], 403); }
    if ($p['status'] !== 'in_progress') { $conn->rollback(); json_out(false, ['message' => t('err_checkin_inactive')], 400); }
    if ($p['last_checkin'] === today()) { $conn->rollback(); json_out(false, ['message' => t('err_already_checked_in')], 400); }

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $streak = ($p['last_checkin'] === $yesterday) ? (int)$p['streak_count'] + 1 : 1;
    $best = max((int)$p['best_streak'], $streak);

    db_exec($conn, "INSERT IGNORE INTO checkins (promise_id, user_id, checkin_date) VALUES (?, ?, CURDATE())", "ii", [$pid, $uid]);
    db_exec($conn, "UPDATE promises SET streak_count = ?, best_streak = ?, last_checkin = CURDATE() WHERE id = ?", "iii", [$streak, $best, $pid]);
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    json_out(false, ['message' => t('something_wrong')], 500);
}

$milestone = in_array($streak, PE_STREAK_MILESTONES, true);
if ($milestone) notify($conn, $uid, null, 'streak', $pid, (string)$streak, 0, $p['title']);

$p['streak_count'] = $streak; $p['best_streak'] = $best; $p['last_checkin'] = today();
$week = array_column(db_all($conn, "SELECT checkin_date FROM checkins WHERE promise_id = ? AND checkin_date >= CURDATE() - INTERVAL 6 DAY", "i", [$pid]), 'checkin_date');
json_out(true, ['streak' => $streak, 'milestone' => $milestone, 'html' => card_streak($p, $week, true)]);
