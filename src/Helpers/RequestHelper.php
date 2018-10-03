<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Helpers;

use Psr\Http\Message\RequestInterface;

/**
 * Class RequestHelper
 * @package hamburgscleanest\GuzzleAdvancedThrottle\Helpers
 */
class RequestHelper
{

    /**
     * @param RequestInterface $request
     * @return array
     */
    public static function getHostAndPath(RequestInterface $request) : array
    {
        $uri = $request->getUri();

        return [
            $uri->getScheme() . '://' . $uri->getHost(),
            $uri->getPath()
        ];
    }

    /**
     * @param RequestInterface $request
     * @return string
     */
    public static function getStorageKey(RequestInterface $request) : string
    {
        $method = $request->getMethod();
        if ($method !== 'GET')
        {
            $contentType = $request->getHeader('Content-Type')[0] ?? null;
            $params = $request->getBody()->getContents();

            return self::_getMethodAndParams(
                $method,
                $contentType === 'application/json' ? self::_decodeJSON($params) : $params
            );
        }

        return self::_getMethodAndParams($method, $request->getUri()->getQuery());
    }

    /**
     * @param string $method
     * @param string $params
     * @return string
     */
    private static function _getMethodAndParams(string $method, string $params) : string
    {
        return $method . '_' . self::_sortParams($params);
    }

    /**
     * @param string $params
     * @return string
     */
    private static function _sortParams(string $params) : string
    {
        $paramArray = \explode('&', $params);
        \sort($paramArray);

        return \implode('&', $paramArray);
    }

    /**
     * @param string $json
     * @return string
     */
    private static function _decodeJSON(string $json) : string
    {
        if(empty($json)) {
            return ''; // or also like in your code just return $json
        }
        return http_build_query(\GuzzleHttp\json_decode($json, true), '', '&');
    }
}