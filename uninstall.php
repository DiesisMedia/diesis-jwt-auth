<?php

/**
 * Uninstall cleanup for Diesis Cloudflare Access JWT Auth.
 *
 * Removes the plugin option from every site and purges the network-wide
 * cached Cloudflare Access signing keys for every issuer that was configured.
 */

declare(strict_types=1);

use Diesis\WpJwtAuth\Settings;
use Diesis\WpJwtAuth\SigningKeyCache;

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// The modules needed here have no Composer dependencies, so load them directly
// and stay independent of whether vendor/ survived the install.
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/TransientStore.php';
require_once __DIR__ . '/src/WordPressTransientStore.php';
require_once __DIR__ . '/src/SigningKeyCache.php';

/**
 * Forget the current site's settings and report the issuer it used, if any.
 */
$diesisWpJwtAuthForgetSite = static function (): string {
    $issuer = Settings::fromStored(get_option(Settings::OPTION, []))->issuer;
    delete_option(Settings::OPTION);

    return $issuer;
};

/** @var array<string, true> $diesisWpJwtAuthIssuers */
$diesisWpJwtAuthIssuers = [];

if (is_multisite()) {
    // Page through the whole network: get_sites() defaults to 100 results.
    $offset = 0;

    do {
        $page = get_sites([
            'fields' => 'ids',
            'number' => 100,
            'offset' => $offset,
            'orderby' => 'id',
            'order' => 'ASC',
        ]);
        $page = is_array($page) ? $page : [];

        foreach ($page as $siteId) {
            if (! is_int($siteId)) {
                continue;
            }

            switch_to_blog($siteId);
            $issuer = $diesisWpJwtAuthForgetSite();
            restore_current_blog();

            if ($issuer !== '') {
                $diesisWpJwtAuthIssuers[$issuer] = true;
            }
        }

        $offset += 100;
    } while (count($page) === 100);
} else {
    $issuer = $diesisWpJwtAuthForgetSite();

    if ($issuer !== '') {
        $diesisWpJwtAuthIssuers[$issuer] = true;
    }
}

foreach (array_keys($diesisWpJwtAuthIssuers) as $diesisWpJwtAuthIssuer) {
    SigningKeyCache::forWordPress($diesisWpJwtAuthIssuer)->purge();
}
