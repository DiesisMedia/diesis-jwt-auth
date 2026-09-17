#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes languages/diesis-jwt-auth.pot from gettext calls in the plugin source.
 * Dates are omitted so CI can diff the file byte-for-byte.
 */

$root = dirname(__DIR__);
$headerVersion = pluginHeaderField($root . '/diesis-jwt-auth.php', 'Version');
$headerName = pluginHeaderField($root . '/diesis-jwt-auth.php', 'Plugin Name');

$files = array_merge(
    [$root . '/diesis-jwt-auth.php'],
    glob($root . '/src/*.php') ?: [],
);

/** @var array<string, array{comment: ?string, refs: list<string>}> */
$entries = [];

foreach ($files as $file) {
    $code = file_get_contents($file);
    if ($code === false) {
        fwrite(STDERR, "Unable to read {$file}\n");
        exit(1);
    }

    $relative = substr($file, strlen($root) + 1);
    $found = preg_match_all(
        '%(?:/\*\s*(translators:.*?)\s*\*/\s*)?(?:esc_html__|__)\(\s*\'((?:\\\\\'|[^\'])*)\'\s*,\s*\'diesis-jwt-auth\'\s*\)%s',
        $code,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    if ($found === false) {
        fwrite(STDERR, "Failed to scan {$file}\n");
        exit(1);
    }

    foreach ($matches[2] as $i => [$rawMsgid, $offset]) {
        $msgid = stripcslashes($rawMsgid);
        $line = substr_count(substr($code, 0, $offset), "\n") + 1;
        $comment = $matches[1][$i][0] !== '' ? trim($matches[1][$i][0]) : null;

        if (! isset($entries[$msgid])) {
            $entries[$msgid] = ['comment' => $comment, 'refs' => []];
        } elseif ($comment !== null && $entries[$msgid]['comment'] === null) {
            $entries[$msgid]['comment'] = $comment;
        }

        $entries[$msgid]['refs'][] = $relative . ':' . $line;
    }
}

ksort($entries, SORT_STRING);

$pot = <<<POT
# Copyright (C) 2026 DIESIS Media - Florian Gratzl
# This file is distributed under the GPL-2.0-or-later.
msgid ""
msgstr ""
"Project-Id-Version: {$headerName} {$headerVersion}\\n"
"Report-Msgid-Bugs-To: https://github.com/DiesisMedia/diesis-jwt-auth/issues\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Domain: diesis-jwt-auth\\n"

POT;

foreach ($entries as $msgid => $entry) {
    $pot .= "\n";
    if ($entry['comment'] !== null) {
        $pot .= '#. ' . $entry['comment'] . "\n";
    }
    foreach ($entry['refs'] as $ref) {
        $pot .= '#: ' . $ref . "\n";
    }
    $pot .= 'msgid "' . addcslashes($msgid, "\0..\37\"\\") . "\"\n";
    $pot .= "msgstr \"\"\n";
}

$target = $root . '/languages/diesis-jwt-auth.pot';
if (file_put_contents($target, $pot) === false) {
    fwrite(STDERR, "Unable to write {$target}\n");
    exit(1);
}

function pluginHeaderField(string $pluginFile, string $field): string
{
    $code = file_get_contents($pluginFile);
    if ($code === false) {
        fwrite(STDERR, "Unable to read {$pluginFile}\n");
        exit(1);
    }

    if (preg_match('/^\s*\*\s*' . preg_quote($field, '/') . ':\s*(.+)$/m', $code, $matches) !== 1) {
        fwrite(STDERR, "Missing plugin header field {$field}\n");
        exit(1);
    }

    return trim($matches[1]);
}
