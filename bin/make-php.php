#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes languages/*.l10n.php from the matching .po catalogs.
 */

$root = dirname(__DIR__);
$catalogs = glob($root . '/languages/diesis-jwt-auth-*.po') ?: [];

if ($catalogs === []) {
    fwrite(STDERR, "No locale catalogs found in languages/\n");
    exit(1);
}

foreach ($catalogs as $po) {
    $messages = translations($po);
    $php = "<?php\n\nreturn array(\n    'messages' => array(\n";
    foreach ($messages as $msgid => $msgstr) {
        $php .= '        ' . var_export($msgid, true) . ' => ' . var_export($msgstr, true) . ",\n";
    }
    $php .= "    ),\n);\n";
    $target = preg_replace('/\.po$/', '.l10n.php', $po);
    if ($target === null || file_put_contents($target, $php) === false) {
        fwrite(STDERR, "Unable to write compiled catalog for {$po}\n");
        exit(1);
    }
}

/**
 * @return array<string, string>
 */
function translations(string $path): array
{
    $map = [];
    $msgid = null;
    $msgstr = null;
    $field = null;

    $flush = static function () use (&$map, &$msgid, &$msgstr, &$field): void {
        if ($msgid !== null && $msgid !== '' && $msgstr !== null) {
            $map[$msgid] = $msgstr;
        }
        $msgid = null;
        $msgstr = null;
        $field = null;
    };

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }

    foreach ($lines as $line) {
        if ($line === '') {
            $flush();
            continue;
        }
        if ($line[0] === '#') {
            continue;
        }
        if (preg_match('/^msgid "(.*)"$/', $line, $matches) === 1) {
            $flush();
            $field = 'msgid';
            $msgid = stripcslashes($matches[1]);
            continue;
        }
        if (preg_match('/^msgstr "(.*)"$/', $line, $matches) === 1) {
            $field = 'msgstr';
            $msgstr = stripcslashes($matches[1]);
            continue;
        }
        if (preg_match('/^"(.*)"$/', $line, $matches) === 1) {
            $chunk = stripcslashes($matches[1]);
            if ($field === 'msgid') {
                $msgid = ($msgid ?? '') . $chunk;
            } elseif ($field === 'msgstr') {
                $msgstr = ($msgstr ?? '') . $chunk;
            }
        }
    }
    $flush();

    return $map;
}
