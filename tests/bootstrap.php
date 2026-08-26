<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (! function_exists('is_email')) {
    function is_email(string $email): string|false
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
    }
}
