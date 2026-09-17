<?php

declare(strict_types=1);

namespace Diesis\JwtAuth\Tests;

use PHPUnit\Framework\TestCase;

final class TranslationCatalogTest extends TestCase
{
    private const POT = __DIR__ . '/../languages/diesis-jwt-auth.pot';
    private const DE_PO = __DIR__ . '/../languages/diesis-jwt-auth-de_DE.po';
    private const DE_PHP = __DIR__ . '/../languages/diesis-jwt-auth-de_DE.l10n.php';

    /** @var array<string, list<string>> */
    private const KEEP_ENGLISH_IN_SENTENCES = [
        'Cloudflare Access authentication required' => ['Cloudflare Access'],
        'DIESIS JWT Auth for Cloudflare Access is incomplete. Reinstall the release ZIP containing its dependencies.' => ['DIESIS JWT Auth for Cloudflare Access'],
        'Issuer and audience are required before enforcement can be enabled.' => ['Issuer', 'Enforcement'],
        'Only exclude a path if the matching Cloudflare Access destination also leaves it public.' => ['Cloudflare Access'],
        'Optional defense in depth. Enter one address per line. Leave empty to trust the Access policy.' => ['Access policy'],
        'Require a valid Access JWT on the configured paths' => ['Access JWT'],
        'The issuer must be an HTTPS cloudflareaccess.com URL.' => ['Issuer'],
        'Validates Cloudflare Access at the WordPress origin. This plugin does not replace the WordPress login.' => ['Cloudflare Access', 'origin'],
    ];

    /** @var list<string> */
    private const KEEP_ENGLISH = [
        'Application audience',
        'DIESIS JWT Auth',
        'DIESIS JWT Auth for Cloudflare Access',
        'Enforcement',
        'Issuer',
    ];

    public function testPortableObjectTemplateContainsAccessDenied(): void
    {
        self::assertContains('Access denied.', self::msgids(self::POT));
    }

    public function testPortableObjectTemplateContainsEveryPluginGettextString(): void
    {
        $msgids = self::msgids(self::POT);

        foreach (self::pluginGettextStrings() as $string) {
            self::assertContains($string, $msgids);
        }
    }

    public function testGermanCatalogTranslatesEveryTemplateString(): void
    {
        $translations = self::translations(self::DE_PO);

        foreach (self::msgids(self::POT) as $msgid) {
            self::assertArrayHasKey($msgid, $translations);
            self::assertNotSame('', $translations[$msgid]);
        }
    }

    public function testGermanCatalogLeavesKeepEnglishLabelsUntranslated(): void
    {
        $translations = self::translations(self::DE_PO);

        foreach (self::KEEP_ENGLISH as $msgid) {
            self::assertSame($msgid, $translations[$msgid]);
        }
    }

    public function testGermanCatalogKeepsEnglishTermsInsideSentences(): void
    {
        $translations = self::translations(self::DE_PO);

        foreach (self::KEEP_ENGLISH_IN_SENTENCES as $msgid => $terms) {
            foreach ($terms as $term) {
                self::assertStringContainsString($term, $translations[$msgid]);
            }
        }
    }

    public function testGermanCatalogTranslatesNonEnglishLabels(): void
    {
        $translations = self::translations(self::DE_PO);

        foreach (self::msgids(self::POT) as $msgid) {
            if (in_array($msgid, self::KEEP_ENGLISH, true)) {
                continue;
            }
            self::assertNotSame($msgid, $translations[$msgid]);
        }
    }

    public function testGermanCompiledCatalogMatchesThePoFile(): void
    {
        self::assertFileExists(self::DE_PHP);
        $compiled = require self::DE_PHP;
        self::assertIsArray($compiled);
        self::assertArrayHasKey('messages', $compiled);
        self::assertSame(self::translations(self::DE_PO), $compiled['messages']);
    }

    public function testPluginHeaderDeclaresLanguagesDomainPath(): void
    {
        $header = file_get_contents(dirname(__DIR__) . '/diesis-jwt-auth.php');
        self::assertNotFalse($header);
        self::assertMatchesRegularExpression('/^\s*\* Domain Path: \/languages$/m', $header);
    }

    public function testRegeneratedPortableObjectTemplateMatchesTheCommittedFile(): void
    {
        $committed = file_get_contents(self::POT);
        self::assertNotFalse($committed);
        passthru('php ' . escapeshellarg(dirname(__DIR__) . '/bin/make-pot.php'), $exit);
        self::assertSame(0, $exit);
        self::assertSame($committed, file_get_contents(self::POT));
    }

    public function testKeepEnglishLabelsHaveTranslatorCommentsInTheTemplate(): void
    {
        $pot = file_get_contents(self::POT);
        self::assertNotFalse($pot);

        foreach (self::KEEP_ENGLISH as $msgid) {
            $quoted = addcslashes($msgid, "\0..\37\"\\");
            self::assertMatchesRegularExpression(
                '/#\\. translators: Keep in English\\..*?^msgid "' . preg_quote($quoted, '/') . '"/sm',
                $pot
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function msgids(string $path): array
    {
        self::assertFileExists($path);

        $msgids = [];
        $current = null;

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^msgid "(.*)"$/', $line, $matches) !== 1) {
                continue;
            }

            $current = stripcslashes($matches[1]);
            if ($current !== '') {
                $msgids[] = $current;
            }
        }

        return $msgids;
    }

    /**
     * @return list<string>
     */
    private static function pluginGettextStrings(): array
    {
        $files = array_merge(
            [dirname(__DIR__) . '/diesis-jwt-auth.php'],
            glob(dirname(__DIR__) . '/src/*.php') ?: [],
        );
        $strings = [];

        foreach ($files as $file) {
            $code = file_get_contents($file);
            self::assertNotFalse($code);

            if (preg_match_all("/(?:esc_html__|__)\\(\\s*'((?:\\\\'|[^'])*)'\\s*,\\s*'diesis-jwt-auth'\\s*\\)/", $code, $matches) !== false) {
                foreach ($matches[1] as $string) {
                    $strings[] = stripcslashes($string);
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @return array<string, string>
     */
    private static function translations(string $path): array
    {
        self::assertFileExists($path);

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

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
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
}
