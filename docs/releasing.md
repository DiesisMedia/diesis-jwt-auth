# Releasing

1. Bump the version in `diesis-jwt-auth.php` and the `Stable tag` in `readme.txt`, and add the changelog entry.
2. Refresh the catalogs: `composer i18n:pot`, then update `Project-Id-Version` in `languages/*.po`, then `composer i18n:php`. The template embeds the plugin version, and `composer test` compares the committed template against a fresh one.
3. Merge to `main`.
4. Push the annotated tag `vX.Y.Z`.

The tag runs [`deploy-wporg.yml`](../.github/workflows/deploy-wporg.yml), which refuses to continue unless the tag, the plugin header and `Stable tag` agree, then builds the release ZIP and hands it to the WordPress.org SVN deploy. Setting the repository variable `WPORG_DEPLOY_DRY_RUN` to anything other than `false` stops it before the SVN commit.
