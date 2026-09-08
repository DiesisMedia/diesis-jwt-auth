<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

/**
 * WordPress adapter for the Settings value: registers the option, adds the
 * options page, renders the form and links to it from the plugins screen.
 */
final class SettingsPage
{
    private const GROUP = 'diesis_wp_jwt_auth';
    private const SLUG = 'diesis-wp-jwt-auth';

    public function register(string $pluginFile): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_filter('plugin_action_links_' . plugin_basename($pluginFile), [$this, 'settingsLink']);
    }

    public function registerSettings(): void
    {
        register_setting(
            self::GROUP,
            Settings::OPTION,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default' => Settings::defaults()->toArray(),
            ]
        );
    }

    public function addSettingsPage(): void
    {
        add_options_page(
            __('DIESIS JWT Auth for Cloudflare Access', 'diesis-wp-jwt-auth'),
            __('DIESIS JWT Auth', 'diesis-wp-jwt-auth'),
            'manage_options',
            self::SLUG,
            [$this, 'render']
        );
    }

    /**
     * @return array<string, bool|string|list<string>>
     */
    public function sanitize(mixed $input): array
    {
        $settings = Settings::parse($input);

        foreach ($settings->problems as $problem) {
            [$code, $message] = match ($problem) {
                SettingsProblem::InvalidIssuer => ['invalid_issuer', __('The issuer must be an HTTPS cloudflareaccess.com URL.', 'diesis-wp-jwt-auth')],
                SettingsProblem::MissingConfiguration => ['missing_configuration', __('Issuer and audience are required before enforcement can be enabled.', 'diesis-wp-jwt-auth')],
            };
            add_settings_error(Settings::OPTION, $code, $message);
        }

        return $settings->toArray();
    }

    /**
     * @param list<string> $links
     * @return list<string>
     */
    public function settingsLink(array $links): array
    {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('options-general.php?page=' . self::SLUG)),
                esc_html__('Settings', 'diesis-wp-jwt-auth')
            )
        );

        return $links;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = Settings::parse(get_option(Settings::OPTION, []));
        $option = Settings::OPTION;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('DIESIS JWT Auth for Cloudflare Access', 'diesis-wp-jwt-auth'); ?></h1>
            <p><?php echo esc_html__('Validates Cloudflare Access at the WordPress origin. This plugin does not replace the WordPress login.', 'diesis-wp-jwt-auth'); ?></p>
            <?php settings_errors($option); ?>
            <form action="options.php" method="post">
                <?php settings_fields(self::GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enforcement', 'diesis-wp-jwt-auth'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($option); ?>[enabled]" value="1" <?php checked($settings->enabled); ?>>
                                <?php echo esc_html__('Require a valid Access JWT on the configured paths', 'diesis-wp-jwt-auth'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-issuer"><?php echo esc_html__('Issuer', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <input class="regular-text" id="diesis-wp-jwt-auth-issuer" name="<?php echo esc_attr($option); ?>[issuer]" type="url" value="<?php echo esc_attr($settings->issuer); ?>" placeholder="https://team.cloudflareaccess.com">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-audience"><?php echo esc_html__('Application audience', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <input class="large-text code" id="diesis-wp-jwt-auth-audience" name="<?php echo esc_attr($option); ?>[audience]" type="text" value="<?php echo esc_attr($settings->audience); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-emails"><?php echo esc_html__('Allowed emails', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-wp-jwt-auth-emails" name="<?php echo esc_attr($option); ?>[allowed_emails]" rows="4"><?php echo esc_textarea(implode("\n", $settings->allowedEmails)); ?></textarea>
                            <p class="description"><?php echo esc_html__('Optional defense in depth. Enter one address per line. Leave empty to trust the Access policy.', 'diesis-wp-jwt-auth'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-paths"><?php echo esc_html__('Protected paths', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-wp-jwt-auth-paths" name="<?php echo esc_attr($option); ?>[protected_paths]" rows="5"><?php echo esc_textarea(implode("\n", $settings->protectedPaths)); ?></textarea>
                            <p class="description"><?php echo esc_html__('One path per line. A trailing * matches a prefix. Query strings are ignored.', 'diesis-wp-jwt-auth'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-wp-jwt-auth-exclusions"><?php echo esc_html__('Excluded paths', 'diesis-wp-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-wp-jwt-auth-exclusions" name="<?php echo esc_attr($option); ?>[excluded_paths]" rows="3"><?php echo esc_textarea(implode("\n", $settings->excludedPaths)); ?></textarea>
                            <p class="description"><?php echo esc_html__('Only exclude a path if the matching Cloudflare Access destination also leaves it public.', 'diesis-wp-jwt-auth'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
