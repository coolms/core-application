<?php

declare(strict_types=1);

namespace CoolMS\Core\Application\Tests\Config;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/** Counts the warnings logged, so a test can see how often an unreadable file was parsed. */
final class CountingLogger extends AbstractLogger
{
    public int $warnings = 0;

    /** @param array<mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if (LogLevel::WARNING === $level) {
            ++$this->warnings;
        }
    }
}
