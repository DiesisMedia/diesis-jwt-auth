<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

/**
 * Why parsed settings could not be taken at face value. Enforcement is
 * switched off whenever a problem is present; the admin page turns them into
 * settings errors.
 */
enum SettingsProblem
{
    case InvalidIssuer;
    case MissingConfiguration;
}
