<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

/**
 * WordPress adapter for the review request: records when enforcement first
 * ran, renders the notice, the settings page footer and the plugin row links,
 * and handles the two clicks that end or postpone the request.
 *
 * ReviewNudge decides whether the notice is due; everything here is wiring.
 */
final class ReviewNudgePage
{
    private const REVIEW_URL = 'https://wordpress.org/support/plugin/diesis-jwt-auth/reviews/#new-post';
    private const GITHUB_URL = 'https://github.com/DiesisMedia/diesis-jwt-auth';

    private const REVIEW_ACTION = 'diesis_jwt_auth_review';
    private const DISMISS_ACTION = 'diesis_jwt_auth_dismiss_review';

    /** The only screens the notice appears on. Everywhere else it would be noise. */
    private const NOTICE_SCREENS = ['dashboard', 'plugins', 'settings_page_' . SettingsPage::SLUG];

    /** The only markup the link list may contain. */
    private const LINK_HTML = ['a' => ['href' => [], 'target' => [], 'rel' => []]];

    private string $basename = '';

    public function register(string $pluginFile): void
    {
        $this->basename = plugin_basename($pluginFile);

        add_action('admin_init', [$this, 'rememberEnforcingSince']);
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('admin_footer-settings_page_' . SettingsPage::SLUG, [$this, 'renderFooter']);
        add_filter('plugin_row_meta', [$this, 'rowMeta'], 10, 2);
        add_action('admin_post_' . self::REVIEW_ACTION, [$this, 'handleReview']);
        add_action('admin_post_' . self::DISMISS_ACTION, [$this, 'handleDismiss']);
    }

    /**
     * Start the clock the first time an admin request sees enforcement on.
     * Settings::parse() only reports enabled when issuer and audience are
     * usable, so this really is the moment the site became protected.
     */
    public function rememberEnforcingSince(): void
    {
        if ($this->enforcingSince() > 0 || ! $this->enforcing()) {
            return;
        }

        add_option(ReviewNudge::ENFORCING_SINCE_OPTION, time());
    }

    public function renderNotice(): void
    {
        if (! in_array($this->currentScreen(), self::NOTICE_SCREENS, true) || ! current_user_can('manage_options')) {
            return;
        }

        if (! ReviewNudge::shouldShow($this->enforcingSince(), $this->enforcing(), $this->state(), time())) {
            return;
        }

        wp_admin_notice(
            esc_html__('Is DIESIS JWT Auth doing its job? A short review on WordPress.org and a star on GitHub help other site owners find it.', 'diesis-jwt-auth')
                . ' ' . $this->links(true),
            ['type' => 'info', 'dismissible' => true]
        );
    }

    /**
     * A permanent line under the settings form. It carries no schedule: whoever
     * opened this page came looking for the plugin.
     */
    public function renderFooter(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<p class="description">'
            . esc_html__('Happy with this plugin? Rate it on WordPress.org or star it on GitHub.', 'diesis-jwt-auth')
            . ' ' . wp_kses($this->links(false), self::LINK_HTML)
            . '</p>';
    }

    /**
     * @param list<string> $links
     * @return list<string>
     */
    public function rowMeta(array $links, string $file): array
    {
        if ($file !== $this->basename) {
            return $links;
        }

        $links[] = $this->link($this->actionUrl(self::REVIEW_ACTION), esc_html__('Rate this plugin', 'diesis-jwt-auth'));
        $links[] = $this->link(
            self::GITHUB_URL,
            /* translators: Keep in English. */
            esc_html__('GitHub', 'diesis-jwt-auth')
        );

        return $links;
    }

    /**
     * Following the review link ends the request for this user before the
     * redirect leaves WordPress.
     */
    public function handleReview(): never
    {
        $this->authorize(self::REVIEW_ACTION);
        $this->save(ReviewNudge::rated($this->state()));

        // wp_safe_redirect() would drop an external host and send the user back to wp-admin.
        wp_redirect(self::REVIEW_URL);
        exit;
    }

    public function handleDismiss(): never
    {
        $this->authorize(self::DISMISS_ACTION);
        $this->save(ReviewNudge::dismissed($this->state(), time()));

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    private function authorize(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'diesis-jwt-auth'), '', ['response' => 403]);
        }

        check_admin_referer($action);
    }

    private function links(bool $withDismissal): string
    {
        $links = [
            $this->link($this->actionUrl(self::REVIEW_ACTION), esc_html__('Write a review', 'diesis-jwt-auth')),
            $this->link(self::GITHUB_URL, esc_html__('Star on GitHub', 'diesis-jwt-auth')),
        ];

        if ($withDismissal) {
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->actionUrl(self::DISMISS_ACTION)),
                esc_html__('Don\'t show this again', 'diesis-jwt-auth')
            );
        }

        return implode(' &middot; ', $links);
    }

    /**
     * @param string $label Already escaped for HTML.
     */
    private function link(string $url, string $label): string
    {
        return sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($url), $label);
    }

    private function actionUrl(string $action): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action=' . $action), $action);
    }

    private function currentScreen(): string
    {
        $screen = get_current_screen();

        return $screen instanceof \WP_Screen ? $screen->id : '';
    }

    private function enforcing(): bool
    {
        return Settings::parse(get_option(Settings::OPTION, []))->enabled;
    }

    private function enforcingSince(): int
    {
        $value = get_option(ReviewNudge::ENFORCING_SINCE_OPTION, 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    private function state(): ReviewNudgeState
    {
        return ReviewNudgeState::parse(get_user_meta(get_current_user_id(), ReviewNudge::USER_META, true));
    }

    private function save(ReviewNudgeState $state): void
    {
        update_user_meta(get_current_user_id(), ReviewNudge::USER_META, $state->toArray());
    }
}
