<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle;

use DateTimeImmutable;

class TimeKeeper
{
    private int $_expirationIntervalSeconds;
    private DateTimeImmutable | null $_expiresAt = null;

    public function __construct(int $intervalInSeconds)
    {
        $this->_expirationIntervalSeconds = $intervalInSeconds;
    }

    public static function create(int $intervalInSeconds): self
    {
        return new static($intervalInSeconds);
    }

    public function getExpiration(): ?DateTimeImmutable
    {
        return $this->_expiresAt;
    }

    public function setExpiration(DateTimeImmutable $expiresAt): self
    {
        $this->_expiresAt = $expiresAt;

        return $this;
    }

    private function _getTimeDiff(): ?int
    {
        if ($this->_expiresAt === null) {
            return null;
        }

        return $this->_expiresAt->getTimestamp() - SystemClock::create()->now()->getTimestamp();
    }

    public function getRemainingSeconds(): int
    {
        if ($this->_expiresAt === null) {
            return $this->_expirationIntervalSeconds;
        }

        $diff = $this->_getTimeDiff();

        return $diff !== null && $diff >= 0 ? $diff : $this->_expirationIntervalSeconds;
    }

    public function isExpired(): bool
    {
        $diff = $this->_getTimeDiff();
        if ($diff === null) {
            return false;
        }

        return $diff <= 0;
    }

    public function reset(): void
    {
        $this->_expiresAt = null;
    }

    /**
     * Initialize the expiration date for the request timer.
     */
    public function start(): void
    {
        $this->_expiresAt = SystemClock::create()->advanceSeconds($this->_expirationIntervalSeconds)->now();
    }
}
