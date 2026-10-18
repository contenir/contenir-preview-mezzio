<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock stopped at one instant, moved on only when a test says so.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(
        private int $timestamp = 1_792_236_000,
    ) {}

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp($this->timestamp);
    }
}
