<?php

/**
 * Uninstall cleanup for Diesis Cloudflare Access JWT Auth.
 *
 * Removes the plugin option from every site and the network-wide cached
 * Cloudflare Access signing keys.
 */

declare(strict_types=1);

use Diesis\WpJwtAuth\Plugin;

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$diesisWpJwtAuthOption = 'diesis_wp_jwt_auth';
$diesisWpJwtAuthAutoload = __DIR__ . '/vendor/autoload.php';

// Without the autoloader the shared key derivation is unavailable; still remove
// the current site's option so uninstalling is never worse than before.
if (! is_readable($diesisWpJwtAuthAutoload)) {
    delete_option($diesisWpJwtAuthOption);

    return;
}

require $diesisWpJwtAuthAutoload;

/** @var array<string, true> $diesisWpJwtAuthIssuers */
$diesisWpJwtAuthIssuers = [];

$diesisWpJwtAuthCollect = static function () use ($diesisWpJwtAuthOption, &$diesisWpJwtAuthIssuers): void {
    $stored = get_option($diesisWpJwtAuthOption);

    if (is_array($stored) && isset($stored['issuer']) && is_string($stored['issuer']) && $stored['issuer'] !== '') {
        $diesisWpJwtAuthIssuers[$stored['issuer']] = true;
    }

    delete_option($diesisWpJwtAuthOption);
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
        $siteIds = is_array($page) ? array_values(array_filter($page, 'is_int')) : [];

        foreach ($siteIds as $siteId) {
            switch_to_blog($siteId);
            $diesisWpJwtAuthCollect();
            restore_current_blog();
        }

        $offset += 100;
    } while (count($siteIds) === 100);
} else {
    $diesisWpJwtAuthCollect();
}

// Site transients are network-wide. delete_site_transient() clears both the
// database rows and any persistent object cache entry, which a direct SQL
// delete would miss when Redis or Memcached backs the site-transient group.
foreach (array_keys($diesisWpJwtAuthIssuers) as $diesisWpJwtAuthIssuer) {
    foreach (Plugin::jwksCacheKeys($diesisWpJwtAuthIssuer) as $diesisWpJwtAuthKey) {
        delete_site_transient($diesisWpJwtAuthKey);
    }
}
