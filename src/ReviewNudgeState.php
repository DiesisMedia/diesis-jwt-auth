<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

/**
 * What one user has done with the review request, as an immutable value.
 *
 * Persisted as a single user meta row, so the whole history of the request is
 * read, written and removed in one place. Nothing here decides anything; the
 * schedule lives in ReviewNudge.
 */
final class ReviewNudgeState
{
    public function __construct(
        /** Unix time of the first dismissal, 0 while the request has never been dismissed. */
        public readonly int $firstDismissedAt = 0,
        public readonly int $dismissals = 0,
        /** Set once the user rated the plugin or dismissed the last reminder. */
        public readonly bool $done = false,
    ) {
    }

    /**
     * Parse the stored meta row. Anything unexpected reads as a fresh state,
     * which at worst shows the request once more.
     */
    public static function parse(mixed $value): self
    {
        $value = is_array($value) ? $value : [];

        return new self(
            self::positiveInt($value['first_dismissed_at'] ?? null),
            self::positiveInt($value['dismissals'] ?? null),
            ($value['done'] ?? false) === true,
        );
    }

    /**
     * @return array{first_dismissed_at: int, dismissals: int, done: bool}
     */
    public function toArray(): array
    {
        return [
            'first_dismissed_at' => $this->firstDismissedAt,
            'dismissals' => $this->dismissals,
            'done' => $this->done,
        ];
    }

    private static function positiveInt(mixed $value): int
    {
        return is_int($value) && $value > 0 ? $value : 0;
    }
}
