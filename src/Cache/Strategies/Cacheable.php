<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies;

use GuzzleHttp\Promise\PromiseInterface;
use hamburgscleanest\GuzzleAdvancedThrottle\Helpers\ResponseHelper;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\CacheStrategy;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class Cacheable implements CacheStrategy
{
    private StorageInterface $_storage;

    public function __construct(StorageInterface $storage = null)
    {
        $this->_storage = $storage;
    }

    public function request(RequestInterface $request, callable $handler): PromiseInterface
    {
        return $handler()->then(function(ResponseInterface $response) use ($request) {
            $this->_saveResponse($request, $response);

            return $response;
        });
    }

    protected function _saveResponse(RequestInterface $request, ResponseInterface $response): void
    {
        if (ResponseHelper::hasErrorStatusCode($response)) {
            return;
        }

        $this->_storage->saveResponse($request, $response);
    }

    protected function _getResponse(RequestInterface $request): ?ResponseInterface
    {
        return $this->_storage->getResponse($request);
    }
}
