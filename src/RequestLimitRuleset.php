<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle;

use GuzzleHttp\Promise\PromiseInterface;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Adapters\ArrayAdapter;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Adapters\LaravelAdapter;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\CacheStrategy;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies\Cache;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies\ForceCache;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies\NoCache;
use hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\HostNotDefinedException;
use hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownCacheStrategyException;
use hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownStorageAdapterException;
use Illuminate\Config\Repository;
use Psr\Http\Message\RequestInterface;

/**
 * Class RequestLimitRuleset
 * @package hamburgscleanest\GuzzleAdvancedThrottle
 */
class RequestLimitRuleset
{

    /** @var array */
    private const STORAGE_MAP = [
        'array'   => ArrayAdapter::class,
        'laravel' => LaravelAdapter::class
    ];

    /** @var array */
    private const CACHE_STRATEGIES = [
        'no-cache'    => NoCache::class,
        'cache'       => Cache::class,
        'force-cache' => ForceCache::class
    ];

    /** @var array */
    private $_rules;

    /** @var StorageInterface */
    private $_storage;

    /** @var CacheStrategy */
    private $_cacheStrategy;

    /** @var Repository */
    private $_config;

    /**
     * RequestLimitRuleset constructor.
     * @param array $rules
     * @param string $cacheStrategy
     * @param string|null $storageAdapter
     * @param Repository|null $config
     * @throws \hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownCacheStrategyException
     * @throws \hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownStorageAdapterException
     */
    public function __construct(array $rules, string $cacheStrategy = 'no-cache', string $storageAdapter = 'array', Repository $config = null)
    {
        $this->_rules = $rules;
        $this->_config = $config;
        $this->_setStorageAdapter($storageAdapter);
        $this->_setCacheStrategy($cacheStrategy);
    }

    /**
     * @param string $adapterName
     * @throws \hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownStorageAdapterException
     */
    private function _setStorageAdapter(string $adapterName) : void
    {
        // Storing this so we can potentially change it later
        $adapterClassName = $adapterName;

        // Set the actual class name from the map
        if (isset(self::STORAGE_MAP[$adapterName]))
        {
            $adapterClassName = self::STORAGE_MAP[$adapterName];
        }

        if (!in_array(StorageInterface::class, class_implements($adapterClassName)))
        {
            // Make sure we have the default classes in the array for the exception
            // This is an ugly way to do this...
            foreach (self::STORAGE_MAP as $key => $klass)
            {
                class_exists($klass);
            }

            $validStrategies = array_filter(get_declared_classes(), function($className) {
                    return in_array(StorageInterface::class, class_implements($className));
                }
            );
            throw new UnknownStorageAdapterException($adapterClassName, $validStrategies);
        }

        $this->_storage = new $adapterClassName($this->_config);
    }

    /**
     * @param string $cacheStrategy
     * @throws \hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownCacheStrategyException
     */
    private function _setCacheStrategy(string $cacheStrategy) : void
    {
        // Storing this so we can potentially change it later
        $cacheStrategyClassName = $cacheStrategy;

        // Set the actual class name from the map
        if (isset(self::CACHE_STRATEGIES[$cacheStrategy]))
        {
            $cacheStrategyClassName = self::CACHE_STRATEGIES[$cacheStrategy];
        }

        if (!in_array(CacheStrategy::class, class_implements($cacheStrategyClassName)))
        {
            // Make sure we have the default classes in the array for the exception
            // This is an ugly way to do this...
            foreach (self::CACHE_STRATEGIES as $key => $klass)
            {
                class_exists($klass);
            }

            $validStrategies = array_filter(get_declared_classes(), function($className) {
                return in_array(CacheStrategy::class, class_implements($className));
            });
            throw new UnknownCacheStrategyException($cacheStrategyClassName, $validStrategies);
        }

        $this->_cacheStrategy = new $cacheStrategyClassName($this->_storage);
    }

    /**
     * @param array $rules
     * @param string $cacheStrategy
     * @param string $storageAdapter
     * @return static
     * @throws \hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownStorageAdapterException
     * @throws \hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownCacheStrategyException
     */
    public static function create(array $rules, string $cacheStrategy = 'no-cache', string $storageAdapter = 'array')
    {
        return new static($rules, $cacheStrategy, $storageAdapter);
    }

    /**
     * @param RequestInterface $request
     * @param callable $handler
     * @return PromiseInterface
     */
    public function cache(RequestInterface $request, callable $handler) : PromiseInterface
    {
        return $this->_cacheStrategy->request($request, $handler);
    }

    /**
     * @return RequestLimitGroup
     * @throws \Exception
     * @throws HostNotDefinedException
     */
    public function getRequestLimitGroup() : RequestLimitGroup
    {
        $requestLimitGroup = new RequestLimitGroup();
        foreach ($this->_rules as $host => $rules)
        {
            if (!\is_string($host))
            {
                throw new HostNotDefinedException();
            }

            $requestLimitGroup->addRules($host, $rules, $this->_storage);
        }

        return $requestLimitGroup;
    }
}