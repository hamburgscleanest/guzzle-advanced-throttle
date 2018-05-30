<?php
/**
 * Created by PhpStorm.
 * User: jporter
 * Date: 5/29/18
 * Time: 7:50 PM
 */

namespace hamburgscleanest\GuzzleAdvancedThrottle\Tests;


use GuzzleHttp\Promise\PromiseInterface;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\CacheStrategy;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface;
use Psr\Http\Message\RequestInterface;

class DummyCacheStrategy implements CacheStrategy
{

    /**
     * CacheStrategy constructor.
     * @param null|StorageInterface $storage
     */
    public function __construct(?StorageInterface $storage = null)
    {
    }

    /**
     * @param RequestInterface $request
     * @param callable $handler
     * @return PromiseInterface
     */
    public function request(RequestInterface $request, callable $handler): PromiseInterface
    {
        return $handler();
    }
}