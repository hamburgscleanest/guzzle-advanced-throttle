<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Middleware;

use hamburgscleanest\GuzzleAdvancedThrottle\RequestLimitRuleset;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ThrottleMiddleware
{
    private RequestLimitRuleset $_requestLimitRuleset;

    public function __construct(RequestLimitRuleset $requestLimitRuleset)
    {
        $this->_requestLimitRuleset = $requestLimitRuleset;
    }

    public function __invoke(): callable
    {
        return $this->handle();
    }

    public function handle(): callable
    {
        return function(callable $handler): callable {
            return function(RequestInterface $request, array $options) use ($handler) {
                return $this->_requestLimitRuleset->cache($request, $this->_requestHandler($handler, $request, $options));
            };
        };
    }

    private function _requestHandler(callable $handler, RequestInterface $request, array $options): callable
    {
        return function() use ($handler, $request, $options) {
            $requestLimitGroup = $this->_requestLimitRuleset->getRequestLimitGroup();
            if (!$requestLimitGroup->canRequest($request, $options)) {
                throw new TooManyRequestsHttpException(
                    $requestLimitGroup->getRetryAfter(),
                    'The rate limit was exceeded. Please try again later.'
                );
            }

            return $handler($request, $options);
        };
    }
}
