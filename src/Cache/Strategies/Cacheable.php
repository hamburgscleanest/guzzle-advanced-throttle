<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies;

use GuzzleHttp\Promise\PromiseInterface;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Helpers\ResponseHelper;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\CacheStrategy;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Cache
 * @package hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies
 */
class Cacheable implements CacheStrategy
{

    /** @var StorageInterface */
    private $_storage;

    /**
     * Cachable constructor.
     * @param null|StorageInterface $storage
     */
    public function __construct(StorageInterface $storage = null)
    {
        $this->_storage = $storage;
    }

    /**
     * @param RequestInterface $request
     * @param callable $handler
     * @return PromiseInterface
     */
    public function request(RequestInterface $request, callable $handler) : PromiseInterface
    {
        return $handler()->then(function(ResponseInterface $response) use ($request)
        {
            $this->_saveResponse($request, $response);

            return $response;
        });
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    protected function _saveResponse(RequestInterface $request, ResponseInterface $response) : void
    {
        if (ResponseHelper::hasErrorStatusCode($response))
        {
            return;
        }

        $this->_storage->saveResponse($request, $response);
    }

    /**
     * @param RequestInterface $request
     * @return null|ResponseInterface
     */
    protected function _getResponse(RequestInterface $request) : ? ResponseInterface
    {
        return $this->_storage->getResponse($request);
    }
}