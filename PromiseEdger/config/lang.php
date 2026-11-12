<?php
// lang.php - English / Urdu (اردو) support. Strings live in translations.php

const SUPPORTED_LANGS = ['en', 'ur'];

function detectLang(): string {
    $l = $_COOKIE['pe_lang'] ?? 'en';
    return in_array($l, SUPPORTED_LANGS, true) ? $l : 'en';
}

$GLOBALS['PE_LANG'] = detectLang();

function lang(): string { return $GLOBALS['PE_LANG']; }
function is_rtl(): bool { return lang() === 'ur'; }
function dir_attr(): string { return is_rtl() ? 'rtl' : 'ltr'; }

/** Translate. t('key') or t('key', $a, $b) with sprintf-style %s / %d */
function t(string $key, ...$args) {
    static $dict = null;
    if ($dict === null) $dict = require __DIR__ . '/translations.php';
    $s = $dict[lang()][$key] ?? $dict['en'][$key] ?? $key;
    if (is_array($s) || !$args) return $s;
    return vsprintf($s, $args);
}

/** Back / forward arrow icons that flip in RTL */
function i_back(): string { return is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left'; }
function i_next(): string { return is_rtl() ? 'bi-arrow-left' : 'bi-arrow-right'; }
