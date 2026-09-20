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

Install from the WordPress plugin directory, or download `diesis-jwt-auth-<version>.zip` from the latest GitHub release and upload it under **Plugins > Add New Plugin > Upload Plugin**. The step-by-step setup is in `readme.txt`.

## Configuration

Open **Settings > DIESIS JWT Auth**. A fresh installation uses these defaults:

| Setting | Default |
| --- | --- |
| Enforcement | Disabled |
| Issuer | Empty. Enter your Cloudflare Access team domain. |
| Application audience | Empty. Enter the audience tag of your Access application. |
| Allowed emails | Empty. No additional email allowlist at the origin. A valid user token with an email claim is still required. |
| Protected paths | The three lines below. |
| Excluded paths | Empty. No exclusions. |

Copy this block into **Protected paths**, one path per line:

```text
/wp-login.php*
/wp-admin
/wp-admin/*
```

These defaults apply when WordPress is installed at the domain root. If its login and admin URLs start with `/wordpress/`, use this block instead:

```text
/wordpress/wp-login.php*
/wordpress/wp-admin
/wordpress/wp-admin/*
```

Use the path prefix from your actual login and admin URLs. Do not include the domain. Leaving **Protected paths** empty restores the default three paths; it does not disable protection.

**Issuer** and **Application audience** have no shared default. Copy them from your own self-hosted application in Cloudflare Zero Trust. The issuer has the form `https://your-team.cloudflareaccess.com`, with your team name and no extra path. The audience is the application's audience tag, not its name or your site URL.

Leave **Allowed emails** and **Excluded paths** empty for the default setup. To add an email allowlist, enter your actual permitted addresses, one per line. This is a second check at the origin and should match the Cloudflare Access policy.

Save with **Enforcement** disabled, confirm that your Cloudflare Access application covers the same paths, then enable enforcement and save again. Test access through Cloudflare and confirm that a direct request to a protected origin path without a valid Access JWT receives HTTP 403.

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
bin/build-release.sh 1.4.1
```

`composer install` also runs [Strauss](https://github.com/BrianHenryIE/strauss) through `bin/strauss.sh`, which copies `firebase/php-jwt` into `vendor-prefixed/` under the `Diesis\JwtAuth\Vendor\` namespace and removes the unprefixed copy. Another plugin bundling a different version of the library can therefore not replace ours. The script downloads a pinned `strauss.phar` on first use and verifies its checksum; both `vendor-prefixed/` and the phar are ignored by git.

## Releasing

1. Bump the version in `diesis-jwt-auth.php` and the `Stable tag` in `readme.txt`, and add the changelog entry.
2. Merge to `main`.
3. Push the annotated tag `vX.Y.Z`.

The tag runs [`deploy-wporg.yml`](.github/workflows/deploy-wporg.yml), which refuses to continue unless the tag, the plugin header and `Stable tag` agree, then builds the release ZIP and hands it to the WordPress.org SVN deploy. Setting the repository variable `WPORG_DEPLOY_DRY_RUN` to anything other than `false` stops it before the SVN commit.

## License

Copyright (C) 2026 DIESIS Media - Florian Gratzl

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. See [LICENSE](LICENSE) for the full text.
