=== DIESIS JWT Auth for Cloudflare Access ===
Contributors: diesismedia
Tags: cloudflare, access, jwt, authentication, security
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Validates Cloudflare Access JWTs at the WordPress origin so protected paths stay closed to requests that bypass Cloudflare.

== Description ==

Cloudflare Access can put a login in front of `/wp-admin` and `/wp-login.php`. It only helps if every request really passes through Cloudflare. Without Cloudflare Tunnel, anyone who knows the origin's address can reach WordPress directly and skip Access entirely.

DIESIS JWT Auth for Cloudflare Access closes that gap. For the paths you choose, WordPress itself checks the `Cf-Access-Jwt-Assertion` header that Cloudflare Access adds to authenticated requests. A request without a valid token is answered with HTTP 403 before WordPress does anything else.

**What it does**

* Verifies the Access JWT signature (RS256) against the signing keys of your Cloudflare Access team.
* Checks expiry, issuer and the application audience of your Access application.
* Optionally restricts access to a list of email addresses as a second check at the origin.
* Protects the paths you configure, with prefix matching and explicit exclusions.
* Caches signing keys for 12 hours and refreshes them when Cloudflare rotates keys.

**What it does not do**

* It does not replace the WordPress login and does not create or log in users. Visitors pass Cloudflare Access first and then sign in to WordPress as usual.
* It does not accept Cloudflare Access service tokens. Those carry no email claim and are always denied. Keep machine-to-machine paths such as cron, XML-RPC or the REST API out of the protected paths, or leave them public in both WordPress and the matching Access destination.

**Safe defaults**

Enforcement only runs when the settings are complete and the issuer is an HTTPS `cloudflareaccess.com` URL. Incomplete or invalid settings disable enforcement instead of locking you out. If Cloudflare's key endpoint is temporarily unreachable, a previously cached key set keeps working.

**Third-party service**

To verify tokens the plugin downloads the public signing keys of your Cloudflare Access team from the issuer you configure, for example `https://your-team.cloudflareaccess.com/cdn-cgi/access/certs`. No site data is sent; the request is a plain download of public keys, repeated at most every 12 hours or after a key rotation. Cloudflare's terms and privacy policy apply to that endpoint: [Terms](https://www.cloudflare.com/terms/), [Privacy policy](https://www.cloudflare.com/privacypolicy/).

Source code, issues and support: [github.com/DiesisMedia/diesis-jwt-auth](https://github.com/DiesisMedia/diesis-jwt-auth)

== Installation ==

1. Create a self-hosted application in Cloudflare Zero Trust that covers your WordPress login and admin paths, and note the team domain (the issuer) and the application audience tag.
2. Install the plugin from the WordPress plugin directory or upload the ZIP under Plugins > Add New Plugin > Upload Plugin, then activate it.
3. Open Settings > DIESIS JWT Auth.
4. Enter the issuer, for example `https://your-team.cloudflareaccess.com`, and the application audience. Save with enforcement still disabled.
5. Check that the protected paths match the paths your Access application covers. Use the copyable defaults below, adjusting the prefix if WordPress is installed in a subdirectory.
6. Enable enforcement and save.
7. Test twice: once through your Cloudflare URL, which should work, and once directly against the origin, which should return 403.

= Default settings =

A fresh installation starts with these values:

* Enforcement: disabled.
* Issuer: empty. Enter your own Cloudflare Access team domain, such as `https://your-team.cloudflareaccess.com`, with no extra path.
* Application audience: empty. Copy the audience tag of your self-hosted Access application from Cloudflare Zero Trust. This is not the application name or your site URL.
* Allowed emails: empty. There is no additional email allowlist at the origin. A valid user token with an email claim is still required.
* Protected paths: the three lines below.
* Excluded paths: empty. There are no exclusions.

Copy these lines into Protected paths, one per line, for WordPress installed at the domain root:

    /wp-login.php*
    /wp-admin
    /wp-admin/*

If your login and admin URLs start with `/wordpress/`, use:

    /wordpress/wp-login.php*
    /wordpress/wp-admin
    /wordpress/wp-admin/*

Use the path prefix from your actual login and admin URLs, without the domain. Leaving Protected paths empty restores the default three paths; it does not disable protection.

Issuer and Application audience are specific to your Cloudflare setup and have no shared default. Leave Allowed emails and Excluded paths empty for the default setup. If you add an email allowlist, use your actual permitted addresses, one per line, matching your Access policy.

== Frequently Asked Questions ==

= I excluded a path in WordPress, but Cloudflare still asks me to log in. =

Exclusions in this plugin only stop the origin check. Cloudflare Access decides on its own which paths it intercepts. Leave the path public in the Access application as well.

= Why does a request get a 403? =

Any failed check ends in 403: no `Cf-Access-Jwt-Assertion` header, a token not signed with RS256, an invalid or expired signature, a wrong issuer or audience, a missing email claim, or an email that is not on the allowed list. With `WP_DEBUG` enabled the reason is written to the PHP error log.

= I locked myself out. How do I get back in? =

Make sure you are opening the site through Cloudflare, not through the origin's own address, and that the Access application covers the same paths as the plugin. If you need to disable the plugin without admin access, rename or delete its folder under `wp-content/plugins/` via SFTP or your host's file manager.

= What happens when Cloudflare's certificate endpoint is down? =

A cached key set stays valid for 12 hours and keeps working. Only if there are no cached keys at all and Cloudflare cannot be reached are protected requests denied.

= Does the plugin work with Cloudflare Access service tokens? =

No. Service tokens carry a `common_name` instead of an email and are always denied. Keep paths used by machines out of the protected paths.

= How do I get rid of the review notice? =

Follow the review link: that ends it for your account for good. Dismissing the notice instead brings it back after three months, after a year and after two years; dismissing that last reminder ends it as well. The notice only ever appears on the Dashboard, the Plugins screen and the plugin's own settings page, and only once enforcement has been running for two weeks.

= Does it work on multisite? =

Yes. Settings are per site, and uninstalling cleans up every site of the network.

== Screenshots ==

1. The settings page: enforcement toggle, issuer, application audience, optional allowed emails, protected and excluded paths.

== Changelog ==

= 1.4.1 =
* The plugin asks for a review on WordPress.org and a star on GitHub: a dismissible notice after two weeks of enforcement, a line on the settings page, and two links in the plugin row. Dismissing the notice brings it back after three months, a year and two years, then never again; following the review link ends it straight away. Uninstalling removes everything it stored.

= 1.4.0 =
* The plugin is translatable. It ships catalogs for de_DE, es_ES, it_IT, zh_CN, ja, and pt_BR; further languages can come from translate.wordpress.org.
* Documented all default settings, with copyable protected paths for root and subdirectory installations.

= 1.3.0 =
* The plugin slug and text domain are now `diesis-jwt-auth`; the option and cached key names follow it.

= 1.2.0 =
* Renamed to DIESIS JWT Auth for Cloudflare Access; first release in the WordPress plugin directory.
* The bundled JWT library is now namespaced to this plugin, so another plugin shipping a different version cannot replace it.
* Fixed the release build appending to an existing ZIP.
* The settings page and menu entry carry the plugin name.
* The release package contains only the files the plugin needs; development and agent files stay out.

= 1.1.0 =
* An issuer that is not an HTTPS cloudflareaccess.com URL, or settings missing the issuer or audience, now disable enforcement instead of denying access. A bad option row can no longer lock the site.
* During a Cloudflare outage without cached keys, a protected request makes one key fetch instead of two and is denied with the reason `keys_unavailable`.
* The key refresh throttle counts every fetch attempt, so an unknown key id costs at most one extra fetch per five minutes.
* Uninstall no longer depends on the vendor directory.

= 1.0.1 =
* Security: request paths are canonicalized before matching, so encoding and dot-segment tricks such as `/a/../wp-login.php` can no longer dodge a protected path.
* Uninstall removes the option from every site on a multisite network and clears cached keys from persistent object caches.
* Accept boolean and integer `true` for the enforcement flag on programmatic option updates.

= 1.0.0 =
* Initial release: validates Cloudflare Access JWTs at the WordPress origin, protects configurable login and admin paths, optional email allowlist.

== Upgrade Notice ==

= 1.4.1 =
Adds a review request in the admin. No settings change needed.

= 1.4.0 =
The plugin UI follows the WordPress language. No settings change needed.

= 1.3.0 =
Plugin slug renamed to diesis-jwt-auth. Installs of the earlier diesis-wp-jwt-auth folder are separate plugins: install this one, enter or copy the settings, then delete the old folder.

= 1.2.0 =
Plugin renamed. Settings and behaviour are unchanged; no action needed.
