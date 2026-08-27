<?php

/**
 * Uninstall cleanup for Diesis Cloudflare Access JWT Auth.
 *
 * Removes the plugin option and the cached Cloudflare Access signing keys.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$diesisWpJwtAuthOption = 'diesis_wp_jwt_auth';

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids']) as $diesisWpJwtAuthSiteId) {
        switch_to_blog((int) $diesisWpJwtAuthSiteId);
        delete_option($diesisWpJwtAuthOption);
        restore_current_blog();
    }
} else {
    delete_option($diesisWpJwtAuthOption);
}

// Delete the network-wide JWKS site transients. They are keyed by an issuer
// hash and their timeout twins, so no single known name addresses them.
global $wpdb;

$diesisWpJwtAuthLike = '_site_transient_%' . $wpdb->esc_like('diesis_wp_jwt_auth_jwks') . '%';

if (is_multisite()) {
    $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $diesisWpJwtAuthLike)
    );
} else {
    $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $diesisWpJwtAuthLike)
    );
}
