<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

/**
 * When to ask a user for a review, as pure arithmetic on Unix timestamps.
 *
 * The first request waits two weeks after enforcement first ran, so only
 * people whose site has actually been protected for a while are asked, and it
 * stays away while enforcement is off or misconfigured. Dismissing brings it
 * back after three, twelve and twenty-four months, counted from the first
 * dismissal, and then never again. Rating ends it immediately.
 *
 * The key names live here because uninstall.php needs them without loading
 * any WordPress adapter.
 */
final class ReviewNudge
{
    /** Site option holding the Unix time enforcement was first seen running. */
    public const ENFORCING_SINCE_OPTION = 'diesis_jwt_auth_enforcing_since';

    /** User meta holding the ReviewNudgeState row. */
    public const USER_META = 'diesis_jwt_auth_review_nudge';

    private const DAY = 86400;

    private const FIRST_DELAY = 14 * self::DAY;

    /**
     * Seconds after the first dismissal at which each reminder is due.
     *
     * @var list<int>
     */
    private const REMINDERS = [90 * self::DAY, 365 * self::DAY, 730 * self::DAY];

    /**
     * @param int $enforcingSince Unix time enforcement was first seen running, 0 if never.
     * @param bool $enforcing Whether the current settings enable enforcement.
     */
    public static function shouldShow(int $enforcingSince, bool $enforcing, ReviewNudgeState $state, int $now): bool
    {
        if (! $enforcing || $state->done || $enforcingSince <= 0) {
            return false;
        }

        if ($state->dismissals === 0) {
            return $now >= $enforcingSince + self::FIRST_DELAY;
        }

        $due = self::REMINDERS[$state->dismissals - 1] ?? null;

        return $due !== null
            && $state->firstDismissedAt > 0
            && $now >= $state->firstDismissedAt + $due;
    }

    /**
     * Record a dismissal. Dismissing the last reminder ends the request.
     */
    public static function dismissed(ReviewNudgeState $state, int $now): ReviewNudgeState
    {
        $dismissals = $state->dismissals + 1;

        return new ReviewNudgeState(
            $state->firstDismissedAt > 0 ? $state->firstDismissedAt : $now,
            $dismissals,
            $dismissals > count(self::REMINDERS),
        );
    }

    /**
     * Record that the user followed the review link. Whether a review was
     * really written cannot be known, and asking again would be worse than
     * missing one.
     */
    public static function rated(ReviewNudgeState $state): ReviewNudgeState
    {
        return new ReviewNudgeState($state->firstDismissedAt, $state->dismissals, true);
    }
}
