<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle;

use GuzzleHttp\Psr7\Uri;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Adapters\ArrayAdapter;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Class RequestLimiter
 * @package hamburgscleanest\GuzzleAdvancedThrottle
 */
class RequestLimiter
{

    /** @var int */
    private const DEFAULT_MAX_REQUESTS = 120;
    /** @var int */
    private const DEFAULT_REQUEST_INTERVAL = 60;
    /** @var string */
    private $_host;
    /** @var TimeKeeper */
    private $_timekeeper;
    /** @var int */
    private $_requestCount = 0;
    /** @var int */
    private $_maxRequestCount;
    /** @var StorageInterface */
    private $_storage;
    /** @var string */
    private $_storageKey;

    /**
     * RequestLimiter constructor.
     * @param string $host
     * @param int $maxRequests
     * @param int $requestIntervalSeconds
     * @param StorageInterface|null $storage
     * @throws \Exception
     */
    public function __construct(string $host, ?int $maxRequests = self::DEFAULT_MAX_REQUESTS, ?int $requestIntervalSeconds = self::DEFAULT_REQUEST_INTERVAL, StorageInterface $storage = null)
    {
        $this->_storage = $storage ?? new ArrayAdapter();
        $this->_host = $host;
        $this->_maxRequestCount = $maxRequests ?? self::DEFAULT_MAX_REQUESTS;
        $requestInterval = $requestIntervalSeconds ?? self::DEFAULT_REQUEST_INTERVAL;

        $this->_storageKey = $maxRequests . '_' . $requestInterval;
        $this->_restoreState($requestInterval);
    }

    /**
     * @param int $requestIntervalSeconds
     * @throws \Exception
     */
    private function _restoreState(int $requestIntervalSeconds) : void
    {
        $this->_timekeeper = new TimeKeeper($requestIntervalSeconds);

        $requestInfo = $this->_storage->get($this->_host, $this->_storageKey);
        if ($requestInfo === null)
        {
            return;
        }

        $this->_requestCount = $requestInfo->requestCount;
        $this->_timekeeper->setExpiration($requestInfo->expiresAt);
    }

    /**
     * @param string $host
     * @param array $rule
     * @param StorageInterface|null $storage
     * @return RequestLimiter
     * @throws \Exception
     */
    public static function createFromRule(string $host, array $rule, StorageInterface $storage = null) : self
    {
        return new static($host, $rule['max_requests'] ?? null, $rule['request_interval'] ?? null, $storage);
    }

    /**
     * @param string $host
     * @param int $maxRequests
     * @param int $requestIntervalSeconds
     * @param StorageInterface|null $storage
     * @return RequestLimiter
     * @throws \Exception
     */
    public static function create(string $host, ?int $maxRequests = self::DEFAULT_MAX_REQUESTS, ?int $requestIntervalSeconds = self::DEFAULT_REQUEST_INTERVAL, StorageInterface $storage = null) : self
    {
        return new static($host, $maxRequests, $requestIntervalSeconds, $storage);
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function canRequest(RequestInterface $request, array $options = []) : bool
    {
        if (!$this->matches($this->_getHostFromRequestAndOptions($request, $options)))
        {
            return true;
        }

        if ($this->getCurrentRequestCount() >= $this->_maxRequestCount)
        {
            return false;
        }

        $this->_increment();
        $this->_save();

        return true;
    }

    /**
     * @param string $host
     * @return bool
     */
    public function matches(string $host) : bool
    {
        return $this->_host === $host || Wildcard::matches($this->_host, $host);
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @return string
     */
    private function _getHostFromRequestAndOptions(RequestInterface $request, array $options = []) : string
    {
        $uri = $options['base_uri'] ?? $request->getUri();

        return $this->_buildHostUrl($uri);
    }

    /**
     * @param Uri $uri
     * @return string
     */
    private function _buildHostUrl(Uri $uri) : string
    {
        $host = $uri->getHost();
        $scheme = $uri->getScheme();
        if (!empty($host) && !empty($scheme))
        {
            $host = $scheme . '://' . $host;
        } else
        {
            $host = $uri->getPath();
        }

        return $host;
    }

    /**
     * Increment the request counter.
     * @throws \Exception
     */
    private function _increment() : void
    {
        $this->_requestCount++;
        if ($this->_requestCount === 1)
        {
            $this->_timekeeper->start();
        }
    }

    /**
     * Save timer in storage
     */
    private function _save() : void
    {
        $this->_storage->save(
            $this->_host,
            $this->_storageKey,
            $this->_requestCount,
            $this->_timekeeper->getExpiration(),
            $this->getRemainingSeconds()
        );
    }

    /**
     * @return int
     */
    public function getRemainingSeconds() : int
    {
        return $this->_timekeeper->getRemainingSeconds();
    }

    /**
     * @return int
     */
    public function getCurrentRequestCount() : int
    {
        if ($this->_timekeeper->isExpired())
        {
            $this->_timekeeper->reset();
            $this->_requestCount = 0;
        }

        return $this->_requestCount;
    }
}