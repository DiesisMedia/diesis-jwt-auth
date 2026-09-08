# DIESIS JWT Auth for Cloudflare Access

WordPress validates Cloudflare Access at the origin for selected paths. This closes the direct-origin bypass that remains when Access is used without Cloudflare Tunnel.

The plugin does not replace the WordPress login and does not create users. A visitor must first pass Cloudflare Access and then authenticate to WordPress normally.

The user-facing description, installation steps, FAQ and changelog live in [`readme.txt`](readme.txt), the WordPress plugin directory listing. This file covers configuration details and development.

## Requirements

- WordPress 6.8 or newer
- PHP 8.1 or newer with OpenSSL
- A Cloudflare Access self-hosted application
- The site must receive the `Cf-Access-Jwt-Assertion` request header from Cloudflare

## Installation

Install from the WordPress plugin directory, or download `diesis-wp-jwt-auth-<version>.zip` from the latest GitHub release and upload it under **Plugins > Add New Plugin > Upload Plugin**. The step-by-step setup is in `readme.txt`.

## Configuration

Get the issuer and application audience from the Cloudflare Zero Trust dashboard. A typical configuration looks like this:

```text
Issuer:
https://<team-name>.cloudflareaccess.com

Application audience:
<application-audience-tag>

Allowed emails:
admin@example.com
editor@example.com

Protected paths:
/wp-login.php*
/wp-admin
/wp-admin/*
```

The optional email list is a second check at the origin. It should match the Cloudflare Access policy.

## Path matching

- Paths without `*` are exact matches.
- A trailing `*` matches a prefix.
- Query strings are ignored.
- Percent-encoding and repeated slashes are normalized before matching.
- Exclusions take precedence over protected paths.

Do not exclude a path only in WordPress. The corresponding Cloudflare Access destination must also leave it public, otherwise Cloudflare will continue to intercept it.

## Security model

For protected paths, the plugin:

1. reads `Cf-Access-Jwt-Assertion`;
2. requires the RS256 algorithm;
3. downloads signing keys from the configured Cloudflare Access issuer;
4. validates the signature, time constraints, issuer and application audience;
5. optionally checks the authenticated email address;
6. returns HTTP 403 when any check fails.

Enforcement only runs when the stored settings are complete and the issuer is an HTTPS `cloudflareaccess.com` URL. Settings that fail this check, for example an option row written without the settings page, disable enforcement rather than lock the site.

Signing keys are cached for 12 hours. An unknown or rotated key triggers a rate-limited refresh. A previously valid cached key set remains available during a temporary Cloudflare certificate endpoint failure.

The plugin requires a user-bound token with an `email` claim. Cloudflare Access **service tokens** carry a `common_name` instead of an email and are therefore always denied. Keep any machine-to-machine path (cron, XML-RPC, REST) out of the protected paths, or leave it public in both WordPress and the matching Cloudflare Access destination.

## Development

```bash
composer install
composer test
composer analyse
composer lint
bin/build-release.sh 1.1.0
```

`composer install` also runs [Strauss](https://github.com/BrianHenryIE/strauss) through `bin/strauss.sh`, which copies `firebase/php-jwt` into `vendor-prefixed/` under the `Diesis\WpJwtAuth\Vendor\` namespace and removes the unprefixed copy. Another plugin bundling a different version of the library can therefore not replace ours. The script downloads a pinned `strauss.phar` on first use and verifies its checksum; both `vendor-prefixed/` and the phar are ignored by git.

## License

GPL-2.0-or-later
