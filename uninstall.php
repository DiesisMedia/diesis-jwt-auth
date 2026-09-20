<?php

/**
 * Uninstall cleanup for DIESIS JWT Auth for Cloudflare Access.
 *
 * Removes the plugin option from every site and purges the network-wide
 * cached Cloudflare Access signing keys for every issuer that was configured.
 */

declare(strict_types=1);

use Diesis\JwtAuth\ReviewNudge;
use Diesis\JwtAuth\Settings;
use Diesis\JwtAuth\SigningKeyCache;

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// The modules needed here have no Composer dependencies, so load them directly
// and stay independent of whether vendor/ survived the install.
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/TransientStore.php';
require_once __DIR__ . '/src/WordPressTransientStore.php';
require_once __DIR__ . '/src/SigningKeyCache.php';
require_once __DIR__ . '/src/ReviewNudgeState.php';
require_once __DIR__ . '/src/ReviewNudge.php';

(static function (): void {
    /** @var array<string, true> $issuers */
    $issuers = [];

    // Forget the current site's settings, remembering the issuer for the purge.
    // An issuer that fails parsing is left out; any key set cached for it
    // expires on its own within a day.
    $forgetSite = static function () use (&$issuers): void {
        $issuer = Settings::parse(get_option(Settings::OPTION, []))->issuer;
        delete_option(Settings::OPTION);
        delete_option(ReviewNudge::ENFORCING_SINCE_OPTION);

        if ($issuer !== '') {
            $issuers[$issuer] = true;
        }
    };

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
                $forgetSite();
                restore_current_blog();
            }

            $offset += 100;
        } while (count($page) === 100);
    } else {
        $forgetSite();
    }

    foreach (array_keys($issuers) as $issuer) {
        SigningKeyCache::forWordPress($issuer)->purge();
    }

    // User meta is global on multisite, so one pass clears the review request
    // for every user of the network.
    delete_metadata('user', 0, ReviewNudge::USER_META, '', true);
})();
