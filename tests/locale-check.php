<?php

/**
 * @file tests/locale-check.php
 *
 * Two checks in one pass:
 *   1. Every {translate key="plugins.generic.magicLogin.*"} in .tpl files
 *      and __('plugins.generic.magicLogin.*') in .php files has a msgid
 *      in locale/en/locale.po.
 *   2. No plugin key has an empty resolved msgstr (handles multi-line .po
 *      continuation strings correctly).
 *
 * Exit 0 = all clear. Exit 1 = failures found.
 */

declare(strict_types=1);

$root   = dirname(__DIR__);
$errors = 0;

// ── Parse locale/en/locale.po into key => value map ──────────────────────────

$poFile = $root . '/locale/en/locale.po';
if (!file_exists($poFile)) {
    echo "::error::locale/en/locale.po not found\n";
    exit(1);
}

/**
 * Minimal .po parser.
 * Handles both single-line  msgstr "value"
 * and multi-line            msgstr ""\n"line1"\n"line2"
 * Returns ['msgid' => 'resolved msgstr', ...]
 */
function parsePo(string $content): array
{
    $map     = [];
    $lines   = explode("\n", $content);
    $msgid   = null;
    $msgstr  = null;
    $inStr   = null; // 'id' | 'str'

    $unescape = fn(string $s): string =>
        str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $s);

    foreach ($lines as $raw) {
        $line = rtrim($raw);

        if (str_starts_with($line, 'msgid ')) {
            // Save previous pair
            if ($msgid !== null && $msgid !== '') {
                $map[$msgid] = $msgstr ?? '';
            }
            $msgid  = $unescape(trim(substr($line, 6), '"'));
            $msgstr = null;
            $inStr  = 'id';
            continue;
        }

        if (str_starts_with($line, 'msgstr ')) {
            $msgstr = $unescape(trim(substr($line, 7), '"'));
            $inStr  = 'str';
            continue;
        }

        // Continuation line: "more text"
        if ($inStr && preg_match('/^"(.*)"$/', $line, $m)) {
            $chunk = $unescape($m[1]);
            if ($inStr === 'id') {
                $msgid .= $chunk;
            } else {
                $msgstr .= $chunk;
            }
            continue;
        }

        // Blank line or comment — flush
        if (trim($line) === '' || str_starts_with($line, '#')) {
            $inStr = null;
        }
    }
    // Last pair
    if ($msgid !== null && $msgid !== '') {
        $map[$msgid] = $msgstr ?? '';
    }

    return $map;
}

$locale = parsePo(file_get_contents($poFile));
$pluginDefined = array_filter(
    $locale,
    fn($k) => str_starts_with($k, 'plugins.generic.magicLogin.'),
    ARRAY_FILTER_USE_KEY
);

// ── Collect keys from template files ─────────────────────────────────────────

$tplKeys = [];
$iter    = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/templates', FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if ($file->getExtension() !== 'tpl') {
        continue;
    }
    preg_match_all(
        '/\{translate\s+key=["\']([^"\']+)["\']/i',
        file_get_contents($file->getPathname()),
        $m
    );
    foreach ($m[1] as $key) {
        if (str_starts_with($key, 'plugins.generic.magicLogin.')) {
            $tplKeys[$key] = $file->getFilename();
        }
    }
}

// ── Collect keys from PHP files ───────────────────────────────────────────────

$phpKeys = [];
foreach (['classes', 'pages', 'mailables', ''] as $sub) {
    $dir = $sub ? "$root/$sub" : $root;
    if (!is_dir($dir)) {
        continue;
    }
    foreach (new DirectoryIterator($dir) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        preg_match_all(
            '/__\s*\(\s*["\']([^"\']+)["\']/i',
            file_get_contents($file->getPathname()),
            $m
        );
        foreach ($m[1] as $key) {
            if (str_starts_with($key, 'plugins.generic.magicLogin.')) {
                $phpKeys[$key] = $file->getFilename();
            }
        }
    }
}

$allUsed = array_unique(array_merge(array_keys($tplKeys), array_keys($phpKeys)));
sort($allUsed);

// ── Check 1: every used key is defined ───────────────────────────────────────

echo sprintf(
    "Checking %d used key(s) against %d defined in locale/en/locale.po\n",
    count($allUsed),
    count($pluginDefined)
);

$missing = [];
foreach ($allUsed as $key) {
    if (!array_key_exists($key, $locale)) {
        $src      = $tplKeys[$key] ?? ($phpKeys[$key] ?? '?');
        $missing[] = "$key  (from $src)";
        echo "::error::Missing locale key: $key (used in $src)\n";
        $errors++;
    }
}

// ── Check 2: no plugin key has an empty translation ───────────────────────────

$empty = [];
foreach ($pluginDefined as $key => $value) {
    if (trim($value) === '') {
        $empty[] = $key;
        echo "::error::Empty msgstr for key: $key\n";
        $errors++;
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────

if ($errors === 0) {
    echo "\033[32mAll " . count($allUsed) . " used keys present and " . count($pluginDefined) . " defined keys non-empty.\033[0m\n";
    exit(0);
}

echo "\033[31m$errors error(s) found.\033[0m\n";
exit(1);
