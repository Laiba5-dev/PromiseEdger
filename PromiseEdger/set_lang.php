<?php
// File: set_lang.php - language badlo (cookie) aur wapas jao
require_once __DIR__ . '/config/config.php';
$l = $_GET['l'] ?? 'en';
if (in_array($l, SUPPORTED_LANGS, true)) {
    setcookie('pe_lang', $l, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax']);
}
$back = $_GET['back'] ?? 'index.php';
// Sirf apni site ka relative page (open redirect se bachao)
if (!preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $back)) $back = 'index.php';
header("Location: " . $back);
exit();
