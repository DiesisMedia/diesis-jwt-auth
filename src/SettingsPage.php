<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

/**
 * WordPress adapter for the Settings value: registers the option, adds the
 * options page, renders the form and links to it from the plugins screen.
 */
final class SettingsPage
{
    private const GROUP = 'diesis_jwt_auth';
    public const SLUG = 'diesis-jwt-auth';

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
            /* translators: Keep in English. */
            __('DIESIS JWT Auth for Cloudflare Access', 'diesis-jwt-auth'),
            /* translators: Keep in English. */
            __('DIESIS JWT Auth', 'diesis-jwt-auth'),
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
                SettingsProblem::InvalidIssuer => ['invalid_issuer', __('The issuer must be an HTTPS cloudflareaccess.com URL.', 'diesis-jwt-auth')],
                SettingsProblem::MissingConfiguration => ['missing_configuration', __('Issuer and audience are required before enforcement can be enabled.', 'diesis-jwt-auth')],
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
                esc_html__('Settings', 'diesis-jwt-auth')
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
            <h1><?php echo /* translators: Keep in English. */ esc_html__('DIESIS JWT Auth for Cloudflare Access', 'diesis-jwt-auth'); ?></h1>
            <p><?php echo esc_html__('Validates Cloudflare Access at the WordPress origin. This plugin does not replace the WordPress login.', 'diesis-jwt-auth'); ?></p>
            <?php settings_errors($option); ?>
            <form action="options.php" method="post">
                <?php settings_fields(self::GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo /* translators: Keep in English. */ esc_html__('Enforcement', 'diesis-jwt-auth'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($option); ?>[enabled]" value="1" <?php checked($settings->enabled); ?>>
                                <?php echo esc_html__('Require a valid Access JWT on the configured paths', 'diesis-jwt-auth'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-jwt-auth-issuer"><?php echo /* translators: Keep in English. */ esc_html__('Issuer', 'diesis-jwt-auth'); ?></label></th>
                        <td>
                            <input class="regular-text" id="diesis-jwt-auth-issuer" name="<?php echo esc_attr($option); ?>[issuer]" type="url" value="<?php echo esc_attr($settings->issuer); ?>" placeholder="https://team.cloudflareaccess.com">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-jwt-auth-audience"><?php echo /* translators: Keep in English. */ esc_html__('Application audience', 'diesis-jwt-auth'); ?></label></th>
                        <td>
                            <input class="large-text code" id="diesis-jwt-auth-audience" name="<?php echo esc_attr($option); ?>[audience]" type="text" value="<?php echo esc_attr($settings->audience); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-jwt-auth-emails"><?php echo esc_html__('Allowed emails', 'diesis-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-jwt-auth-emails" name="<?php echo esc_attr($option); ?>[allowed_emails]" rows="4"><?php echo esc_textarea(implode("\n", $settings->allowedEmails)); ?></textarea>
                            <p class="description"><?php echo esc_html__('Optional defense in depth. Enter one address per line. Leave empty to trust the Access policy.', 'diesis-jwt-auth'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-jwt-auth-paths"><?php echo esc_html__('Protected paths', 'diesis-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-jwt-auth-paths" name="<?php echo esc_attr($option); ?>[protected_paths]" rows="5"><?php echo esc_textarea(implode("\n", $settings->protectedPaths)); ?></textarea>
                            <p class="description"><?php echo esc_html__('One path per line. A trailing * matches a prefix. Query strings are ignored.', 'diesis-jwt-auth'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="diesis-jwt-auth-exclusions"><?php echo esc_html__('Excluded paths', 'diesis-jwt-auth'); ?></label></th>
                        <td>
                            <textarea class="large-text code" id="diesis-jwt-auth-exclusions" name="<?php echo esc_attr($option); ?>[excluded_paths]" rows="3"><?php echo esc_textarea(implode("\n", $settings->excludedPaths)); ?></textarea>
                            <p class="description"><?php echo esc_html__('Only exclude a path if the matching Cloudflare Access destination also leaves it public.', 'diesis-jwt-auth'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <?php
            /**
             * Runs inside the settings page wrapper, below the form, for
             * anything that belongs on this page but not in the settings.
             */
            do_action('diesis_jwt_auth_after_settings_form');
            ?>
        </div>
        <?php
    }
}
