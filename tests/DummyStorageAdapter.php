<?php
/**
 * Created by PhpStorm.
 * User: jporter
 * Date: 5/29/18
 * Time: 7:53 PM
 */

namespace hamburgscleanest\GuzzleAdvancedThrottle\Tests;


use DateTime;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface;
use hamburgscleanest\GuzzleAdvancedThrottle\RequestInfo;
use Illuminate\Config\Repository;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DummyStorageAdapter implements StorageInterface
{

    /**
     * StorageInterface constructor.
     * @param Repository|null $config
     */
    public function __construct(?Repository $config = null)
    {
    }

    /**
     * @param string $host
     * @param string $key
     * @param int $requestCount
     * @param DateTime $expiresAt
     * @param int $remainingSeconds
     */
    public function save(string $host, string $key, int $requestCount, DateTime $expiresAt, int $remainingSeconds): void
    {
    }

    /**
     * @param string $host
     * @param string $key
     * @return RequestInfo|null
     */
    public function get(string $host, string $key): ?RequestInfo
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function saveResponse(RequestInterface $request, ResponseInterface $response): void
    {
    }

    /**
     * @param RequestInterface $request
     * @return ResponseInterface|null
     */
    public function getResponse(RequestInterface $request): ?ResponseInterface
    {
        return null;
    }
}