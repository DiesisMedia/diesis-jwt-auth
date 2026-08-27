<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

final class Settings
{
    public const OPTION = 'diesis_wp_jwt_auth';

    /**
     * @return array{
     *   enabled: bool,
     *   issuer: string,
     *   audience: string,
     *   allowed_emails: list<string>,
     *   protected_paths: list<string>,
     *   excluded_paths: list<string>
     * }
     */
    public static function get(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $defaults = self::defaults();

        return [
            'enabled' => ($stored['enabled'] ?? false) === true || ($stored['enabled'] ?? '') === '1',
            'issuer' => is_string($stored['issuer'] ?? null) ? rtrim($stored['issuer'], '/') : $defaults['issuer'],
            'audience' => is_string($stored['audience'] ?? null) ? trim($stored['audience']) : $defaults['audience'],
            'allowed_emails' => self::listValue($stored['allowed_emails'] ?? $defaults['allowed_emails']),
            'protected_paths' => self::listValue($stored['protected_paths'] ?? $defaults['protected_paths']),
            'excluded_paths' => self::listValue($stored['excluded_paths'] ?? $defaults['excluded_paths']),
        ];
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_menu', [$this, 'addSettingsPage']);
    }

    public function registerSettings(): void
    {
        register_setting(
            'diesis_wp_jwt_auth',
            self::OPTION,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default' => self::defaults(),
            ]
        );
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('Cloudflare Access JWT', 'diesis-wp-jwt-auth'),
            __('Cloudflare Access JWT', 'diesis-wp-jwt-auth'),
            'manage_options',
            'diesis-wp-jwt-auth',
            [$this, 'render']
        );
    }

    /**
     * @param mixed $input
     * @return array<string, bool|string|list<string>>
     */
    public function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $issuer = isset($input['issuer']) && is_string($input['issuer'])
            ? rtrim(esc_url_raw(trim($input['issuer'])), '/')
            : '';
        $audience = isset($input['audience']) && is_string($input['audience'])
            ? sanitize_text_field($input['audience'])
            : '';
        $enabled = isset($input['enabled']) && in_array($input['enabled'], ['1', 1, true], true);

        if ($issuer !== '' && ! self::isCloudflareAccessIssuer($issuer)) {
            add_settings_error(self::OPTION, 'invalid_issuer', __('The issuer must be an HTTPS cloudflareaccess.com URL.', 'diesis-wp-jwt-auth'));
            $issuer = '';
            $enabled = false;
        }

        if ($enabled && ($issuer === '' || $audience === '')) {
            add_settings_error(self::OPTION, 'missing_configuration', __('Issuer and audience are required before enforcement can be enabled.', 'diesis-wp-jwt-auth'));
            $enabled = false;
        }

        return [
            'enabled' => $enabled,
            'issuer' => $issuer,
            'audience' => $audience,
            'allowed_emails' => self::sanitizeEmails($input['allowed_emails'] ?? ''),
            'protected_paths' => self::sanitizePaths($input['protected_paths'] ?? '', true),
            'excluded_paths' => self::sanitizePaths($input['excluded_paths'] ?? '', false),
        ];
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = self::get();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Cloudflare Access JWT', 'diesis-wp-jwt-auth'); ?></h1>
            <p><?php echo esc_html__('Validates Cloudflare Access at the WordPress origin. This plugin does not replace the WordPress login.', 'diesis-wp-jwt-auth'); ?></p>
            <?php settings_errors(self::OPTION); ?>
            <form action="options.php" method="post">
                <?php settings_fields('diesis_wp_jwt_auth'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enforcement', 'diesis-wp-jwt-auth'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($settings['enabled']); ?>>
                                <?php echo esc_html__('Require a valid Access JWT on the configured paths', 'diesis-wp-jwt-auth'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-issuer"><?php echo esc_html__('Issuer', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <input class="regular-text" id="diesis-wp-jwt-auth-issuer" name="<?php echo esc_attr(self::OPTION); ?>[issuer]" type="url" value="<?php echo esc_attr($settings['issuer']); ?>" placeholder="https://team.cloudflareaccess.com">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-audience"><?php echo esc_html__('Application audience', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <input class="large-text code" id="diesis-wp-jwt-auth-audience" name="<?php echo esc_attr(self::OPTION); ?>[audience]" type="text" value="<?php echo esc_attr($settings['audience']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-emails"><?php echo esc_html__('Allowed emails', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-wp-jwt-auth-emails" name="<?php echo esc_attr(self::OPTION); ?>[allowed_emails]" rows="4"><?php echo esc_textarea(implode("\n", $settings['allowed_emails'])); ?></textarea>
                            <p class="description"><?php echo esc_html__('Optional defense in depth. Enter one address per line. Leave empty to trust the Access policy.', 'diesis-wp-jwt-auth'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-paths"><?php echo esc_html__('Protected paths', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-wp-jwt-auth-paths" name="<?php echo esc_attr(self::OPTION); ?>[protected_paths]" rows="5"><?php echo esc_textarea(implode("\n", $settings['protected_paths'])); ?></textarea>
                            <p class="description"><?php echo esc_html__('One path per line. A trailing * matches a prefix. Query strings are ignored.', 'diesis-wp-jwt-auth'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-exclusions"><?php echo esc_html__('Excluded paths', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-wp-jwt-auth-exclusions" name="<?php echo esc_attr(self::OPTION); ?>[excluded_paths]" rows="3"><?php echo esc_textarea(implode("\n", $settings['excluded_paths'])); ?></textarea>
                            <p class="description"><?php echo esc_html__('Only exclude a path if the matching Cloudflare Access destination also leaves it public.', 'diesis-wp-jwt-auth'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @return array{
     *   enabled: bool,
     *   issuer: string,
     *   audience: string,
     *   allowed_emails: list<string>,
     *   protected_paths: list<string>,
     *   excluded_paths: list<string>
     * }
     */
    private static function defaults(): array
    {
        return [
            'enabled' => false,
            'issuer' => '',
            'audience' => '',
            'allowed_emails' => [],
            'protected_paths' => ['/wp-login.php*', '/wp-admin', '/wp-admin/*'],
            'excluded_paths' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function listValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        return is_string($value) ? self::lines($value) : [];
    }

    private static function isCloudflareAccessIssuer(string $issuer): bool
    {
        $parts = wp_parse_url($issuer);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && isset($parts['host'])
            && is_string($parts['host'])
            && str_ends_with(strtolower($parts['host']), '.cloudflareaccess.com')
            && ! isset($parts['path']);
    }

    /**
     * @return list<string>
     */
    private static function sanitizeEmails(mixed $value): array
    {
        $emails = [];

        foreach (self::listValue($value) as $email) {
            $email = strtolower(sanitize_email($email));

            if ($email !== '' && is_email($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return list<string>
     */
    private static function sanitizePaths(mixed $value, bool $useDefaultsWhenEmpty): array
    {
        $paths = [];

        foreach (self::listValue($value) as $path) {
            $path = trim(sanitize_text_field($path));

            if ($path === '' || ! str_starts_with($path, '/') || substr_count($path, '*') > 1) {
                continue;
            }

            if (str_contains($path, '*') && ! str_ends_with($path, '*')) {
                continue;
            }

            $paths[] = $path;
        }

        $paths = array_values(array_unique($paths));

        if ($useDefaultsWhenEmpty && $paths === []) {
            return self::defaults()['protected_paths'];
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $value): array
    {
        $lines = preg_split('/\R/u', $value);

        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }
}
