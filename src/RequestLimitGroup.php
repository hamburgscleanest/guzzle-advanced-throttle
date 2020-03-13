<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle;

use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface;
use Psr\Http\Message\RequestInterface;
use SplObjectStorage;

/**
 * Class RequestLimitGroup
 * @package hamburgscleanest\GuzzleAdvancedThrottle
 */
class RequestLimitGroup
{

    /** @var SplObjectStorage */
    private $_requestLimiters;

    /** @var int */
    private $_retryAfter = 0;

    /**
     * RequestLimitGroup constructor.
     */
    public function __construct()
    {
        $this->_requestLimiters = new SplObjectStorage();
    }

    /**
     * @return RequestLimitGroup
     */
    public static function create() : self
    {
        return new static();
    }

    /**
     * @return int
     */
    public function getRetryAfter() : int
    {
        return $this->_retryAfter;
    }

    /**
     * We have to cycle through all the limiters (no early return).
     * The timers of each limiter have to be updated despite of another limiter already preventing the request.
     *
     * @param RequestInterface $request
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function canRequest(RequestInterface $request, array $options = []) : bool
    {
        $groupCanRequest = true;
        $this->_requestLimiters->rewind();
        while ($this->_requestLimiters->valid())
        {
            /** @var RequestLimiter $requestLimiter */
            $requestLimiter = $this->_requestLimiters->current();

            $canRequest = $requestLimiter->canRequest($request, $options, false);
            if ($groupCanRequest && !$canRequest)
            {
                $groupCanRequest = false;
                $requestLimiter->canRequest($request, $options, true);
                $this->_retryAfter = $requestLimiter->getRemainingSeconds();
                break;
            }

            $this->_requestLimiters->next();
        }

        if ($groupCanRequest)
        {
            $this->_requestLimiters->rewind();
            while ($this->_requestLimiters->valid())
            {
                /** @var RequestLimiter $requestLimiter */
                $requestLimiter = $this->_requestLimiters->current();

                $requestLimiter->canRequest($request, $options, true);
                $this->_requestLimiters->next();
            }

        }

        return $groupCanRequest;
    }

    /**
     * @param RequestLimiter $requestLimiter
     * @return RequestLimitGroup
     */
    public function addRequestLimiter(RequestLimiter $requestLimiter) : self
    {
        $this->_requestLimiters->attach($requestLimiter);

        return $this;
    }

    /**
     * @param RequestLimiter $requestLimiter
     * @return RequestLimitGroup
     */
    public function removeRequestLimiter(RequestLimiter $requestLimiter) : self
    {
        $this->_requestLimiters->detach($requestLimiter);

        return $this;
    }

    /**
     * @return int
     */
    public function getRequestLimiterCount() : int
    {
        return $this->_requestLimiters->count();
    }

    /**
     * @param string $host
     * @param array $rules
     * @param StorageInterface $storage
     * @throws \Exception
     */
    public function addRules(string $host, array $rules, StorageInterface $storage) : void
    {
        foreach ($rules as $rule)
        {
            $this->addRequestLimiter(
                RequestLimiter::createFromRule(
                    $host,
                    $rule,
                    $storage
                )
            );
        }
    }
}