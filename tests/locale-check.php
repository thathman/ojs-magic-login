<?php

/**
 * @file tests/locale-check.php
 *
 * Verifies that every {translate key="plugins.generic.magicLogin.*"} reference
 * used in the plugin's own Smarty templates has a corresponding msgid entry in
 * locale/en/locale.po.
 *
 * Also checks keys referenced in PHP __() calls within the plugin's classes.
 *
 * Exit code 0 = all keys present.
 * Exit code 1 = one or more keys missing.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

// ── Collect keys from .tpl files ──────────────────────────────────────────────

$tplKeys = [];
$tplFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/templates', FilesystemIterator::SKIP_DOTS)
);
foreach ($tplFiles as $file) {
    if ($file->getExtension() !== 'tpl') {
        continue;
    }
    $content = file_get_contents($file->getPathname());
    // {translate key="plugins.generic.magicLogin.foo.bar"}
    preg_match_all(
        '/\{translate\s+key=["\']([^"\']+)["\']/i',
        $content,
        $matches
    );
    foreach ($matches[1] as $key) {
        if (str_starts_with($key, 'plugins.generic.magicLogin.')) {
            $tplKeys[$key] = $file->getFilename();
        }
    }
}

// ── Collect keys from PHP files ───────────────────────────────────────────────

$phpKeys = [];
$phpDirs = ['classes', 'pages', 'mailables', ''];   // '' = plugin root
foreach ($phpDirs as $subdir) {
    $dir = $subdir ? $root . '/' . $subdir : $root;
    if (!is_dir($dir)) {
        continue;
    }
    foreach (new DirectoryIterator($dir) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        // __('plugins.generic.magicLogin.foo') or __("plugins.generic.magicLogin.foo")
        preg_match_all(
            '/__\s*\(\s*["\']([^"\']+)["\']/i',
            $content,
            $matches
        );
        foreach ($matches[1] as $key) {
            if (str_starts_with($key, 'plugins.generic.magicLogin.')) {
                $phpKeys[$key] = $file->getFilename();
            }
        }
    }
}

$allKeys = array_unique(array_merge(array_keys($tplKeys), array_keys($phpKeys)));
sort($allKeys);

// ── Load locale/en/locale.po ──────────────────────────────────────────────────

$poFile = $root . '/locale/en/locale.po';
if (!file_exists($poFile)) {
    echo "::error::locale/en/locale.po not found\n";
    exit(1);
}
$po = file_get_contents($poFile);

// Build set of defined msgids
preg_match_all('/^msgid\s+"([^"]+)"/m', $po, $m);
$defined = array_flip($m[1]);

// ── Compare ───────────────────────────────────────────────────────────────────

$missing = [];
foreach ($allKeys as $key) {
    if (!isset($defined[$key])) {
        $source = $tplKeys[$key] ?? ($phpKeys[$key] ?? '?');
        $missing[] = ['key' => $key, 'source' => $source];
    }
}

// Report
echo sprintf(
    "Checked %d plugin locale key(s) against locale/en/locale.po (%d defined)\n",
    count($allKeys),
    count(array_filter(array_keys($defined), fn($k) => str_starts_with($k, 'plugins.generic.magicLogin.')))
);

if (empty($missing)) {
    echo "\033[32mAll keys present.\033[0m\n";
    exit(0);
}

echo "\033[31m" . count($missing) . " missing key(s):\033[0m\n";
foreach ($missing as $item) {
    echo "  ::error::Missing locale key '{$item['key']}' (used in {$item['source']})\n";
}
exit(1);
