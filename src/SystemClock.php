<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle;

use DateTimeImmutable;

class SystemClock
{
    use TimeTravel;

    private DateTimeImmutable $_now;

    public function __construct(DateTimeImmutable | null $now = null)
    {
        $this->_now = $now ?? new DateTimeImmutable();
    }

    public static function create(DateTimeImmutable | null $now = null): self
    {
        return new self($now);
    }

    public static function fromTimestamp(int $timestamp): self
    {
        return new self((new DateTimeImmutable())->setTimestamp($timestamp));
    }

    public function now(): DateTimeImmutable
    {
        return $this->_now;
    }
}
