<?php

declare(strict_types=1);

namespace Diesis\JwtAuth\Tests;

use Diesis\JwtAuth\ReviewNudge;
use Diesis\JwtAuth\ReviewNudgeState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewNudgeTest extends TestCase
{
    private const DAY = 86400;
    private const NOW = 1800000000;

    public function testNothingIsAskedBeforeEnforcementEverRan(): void
    {
        self::assertFalse(ReviewNudge::shouldShow(0, true, new ReviewNudgeState(), self::NOW));
    }

    public function testTheFirstRequestWaitsTwoWeeks(): void
    {
        $since = self::NOW - 13 * self::DAY;
        self::assertFalse(ReviewNudge::shouldShow($since, true, new ReviewNudgeState(), self::NOW));

        $since = self::NOW - 14 * self::DAY;
        self::assertTrue(ReviewNudge::shouldShow($since, true, new ReviewNudgeState(), self::NOW));
    }

    public function testNobodyIsAskedWhileEnforcementIsOff(): void
    {
        $since = self::NOW - 30 * self::DAY;
        self::assertFalse(ReviewNudge::shouldShow($since, false, new ReviewNudgeState(), self::NOW));
    }

    /**
     * @return \Generator<string, array{int, int, bool}>
     */
    public static function reminderSchedule(): \Generator
    {
        yield 'first reminder due' => [1, 90, true];
        yield 'before first reminder' => [1, 89, false];
        yield 'second reminder due' => [2, 365, true];
        yield 'before second reminder' => [2, 364, false];
        yield 'third reminder due' => [3, 730, true];
        yield 'before third reminder' => [3, 729, false];
        yield 'no fourth reminder' => [4, 5000, false];
    }

    /**
     * Reminders are counted from the first dismissal, not from the last one.
     */
    #[DataProvider('reminderSchedule')]
    public function testRemindersFollowTheSchedule(int $dismissals, int $daysSinceFirstDismissal, bool $expected): void
    {
        $state = new ReviewNudgeState(self::NOW - $daysSinceFirstDismissal * self::DAY, $dismissals, false);

        self::assertSame($expected, ReviewNudge::shouldShow(self::NOW - 1000 * self::DAY, true, $state, self::NOW));
    }

    public function testADoneStateIsNeverAskedAgain(): void
    {
        $state = new ReviewNudgeState(self::NOW - 1000 * self::DAY, 1, true);

        self::assertFalse(ReviewNudge::shouldShow(self::NOW - 1000 * self::DAY, true, $state, self::NOW));
    }

    public function testTheFirstDismissalStartsTheReminderClock(): void
    {
        $state = ReviewNudge::dismissed(new ReviewNudgeState(), self::NOW);

        self::assertSame(self::NOW, $state->firstDismissedAt);
        self::assertSame(1, $state->dismissals);
        self::assertFalse($state->done);
    }

    public function testLaterDismissalsKeepTheFirstTimestamp(): void
    {
        $state = ReviewNudge::dismissed(new ReviewNudgeState(self::NOW - 90 * self::DAY, 1, false), self::NOW);

        self::assertSame(self::NOW - 90 * self::DAY, $state->firstDismissedAt);
        self::assertSame(2, $state->dismissals);
        self::assertFalse($state->done);
    }

    public function testDismissingTheLastReminderEndsTheRequest(): void
    {
        $state = ReviewNudge::dismissed(new ReviewNudgeState(self::NOW - 730 * self::DAY, 3, false), self::NOW);

        self::assertSame(4, $state->dismissals);
        self::assertTrue($state->done);
    }

    public function testOnlyTheLastReminderAnnouncesThatItIsTheLast(): void
    {
        self::assertFalse(ReviewNudge::endsWithNextDismissal(new ReviewNudgeState()));
        self::assertFalse(ReviewNudge::endsWithNextDismissal(new ReviewNudgeState(self::NOW, 2, false)));
        self::assertTrue(ReviewNudge::endsWithNextDismissal(new ReviewNudgeState(self::NOW, 3, false)));
    }

    public function testFollowingTheReviewLinkEndsTheRequest(): void
    {
        self::assertTrue(ReviewNudge::rated(new ReviewNudgeState())->done);
    }

    public function testAnUnreadableMetaRowReadsAsAFreshState(): void
    {
        $state = ReviewNudgeState::parse('nonsense');

        self::assertSame(0, $state->firstDismissedAt);
        self::assertSame(0, $state->dismissals);
        self::assertFalse($state->done);
    }

    public function testTheStoredShapeRoundTrips(): void
    {
        $state = new ReviewNudgeState(self::NOW, 2, true);

        self::assertEquals($state, ReviewNudgeState::parse($state->toArray()));
    }
}
