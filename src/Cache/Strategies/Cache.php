<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Class Cache
 * @package hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies
 */
class Cache extends Cacheable
{

    /**
     * @param RequestInterface $request
     * @param callable $handler
     * @return PromiseInterface
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException
     */
    public function request(RequestInterface $request, callable $handler) : PromiseInterface
    {
        try
        {
            return parent::request($request, $handler);
        }
        catch (TooManyRequestsHttpException $tooManyRequestsHttpException)
        {
            $response = $this->_getResponse($request);
            if ($response !== null)
            {
                return new FulfilledPromise($response);
            }

            throw $tooManyRequestsHttpException;
        }
    }
}