<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (! function_exists('is_email')) {
    function is_email(string $email): string|false
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return trim($email);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return trim($url);
    }
}

if (! function_exists('wp_parse_url')) {
    /** @return array<string, int|string>|false */
    function wp_parse_url(string $url): array|false
    {
        return parse_url($url);
    }
}

if (! function_exists('add_settings_error')) {
    function add_settings_error(string $setting, string $code, string $message): void
    {
        // No-op stub: the sanitizer records validation errors here.
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
