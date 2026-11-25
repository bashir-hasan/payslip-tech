<?php
declare(strict_types=1);

function lang(): string
{
    static $l = null;
    if ($l !== null) return $l;
    $l = $_SESSION['lang'] ?? ($_COOKIE['pst_lang'] ?? 'en');
    if (!in_array($l, ['en', 'bn'], true)) $l = 'en';
    return $l;
}

function set_lang(string $l): void
{
    if (!in_array($l, ['en', 'bn'], true)) return;
    $_SESSION['lang'] = $l;
    setcookie('pst_lang', $l, ['expires' => time() + 31536000, 'path' => base_path() ?: '/', 'samesite' => 'Lax', 'secure' => is_https(), 'httponly' => true]);
}

/** Translate an English string. Falls back to the English text. */
function t(string $text, ?string $lang = null): string
{
    static $dict = null;
    $lang = $lang ?? lang();
    if ($lang === 'en') return $text;
    if ($dict === null) $dict = require APP . '/lang/bn.php';
    return $dict[$text] ?? $text;
}

/** Translate for a payslip document in a fixed language. */
function tp(string $text, string $lang): string
{
    return t($text, $lang);
}
