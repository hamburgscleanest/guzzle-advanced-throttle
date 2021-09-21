<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class Cache extends Cacheable
{
    public function request(RequestInterface $request, callable $handler): PromiseInterface
    {
        try
        {
            return parent::request($request, $handler);
        }
        catch (TooManyRequestsHttpException $tooManyRequestsHttpException)
        {
            $response = $this->_getResponse($request);
            if ($response !== null) {
                return new FulfilledPromise($response);
            }

            throw $tooManyRequestsHttpException;
        }
    }
}
