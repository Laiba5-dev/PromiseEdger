<?php
// File: report.php - galat promise report karo (JSON)
require_once __DIR__ . '/config/config.php';
$uid = api_guard($conn);

$pid = (int)($_POST['promise_id'] ?? 0);
$reason = (string)($_POST['reason'] ?? '');
$details = mb_substr(trim((string)($_POST['details'] ?? '')), 0, 500);

if (!in_array($reason, PE_REPORT_REASONS, true)) json_out(false, ['message' => t('err_invalid_request')], 400);
$p = getPromiseById($conn, $pid, $uid);
if (!$p) json_out(false, ['message' => t('err_promise_not_found')], 404);
if ((int)$p['user_id'] === $uid) json_out(false, ['message' => t('err_report_own')], 400);

if (db_val($conn, "SELECT 1 FROM reports WHERE promise_id = ? AND reporter_id = ?", "ii", [$pid, $uid])) {
    json_out(false, ['message' => t('err_already_reported')], 409);
}
if ((int)db_val($conn, "SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND created_at > NOW() - INTERVAL 1 HOUR", "i", [$uid]) >= 10) {
    json_out(false, ['message' => t('err_too_many_reports')], 429);
}
db_exec($conn, "INSERT INTO reports (promise_id, reporter_id, reason, details) VALUES (?, ?, ?, ?)", "iiss", [$pid, $uid, $reason, $details !== '' ? $details : null]);
json_out(true, ['message' => t('report_sent')]);
