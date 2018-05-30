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
     * Sets internal storage adapter for this rule set.
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

        if (!$this->_implementsInterface($adapterClassName, StorageInterface::class))
        {
            $validStrategies = \array_filter(get_declared_classes(), function($className) {
                    return $this->_implementsInterface($className, StorageInterface::class);
                }
            );
            throw new UnknownStorageAdapterException($adapterClassName,
                $validStrategies + \array_values(self::STORAGE_MAP));
        }

        $this->_storage = new $adapterClassName($this->_config);
    }

    /**
     * Sets the caching strategy for this rule set.
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

        if (!$this->_implementsInterface($cacheStrategyClassName, CacheStrategy::class))
        {
            $validStrategies = \array_filter(get_declared_classes(), function($className) {
                return $this->_implementsInterface($className, CacheStrategy::class);
            });

            throw new UnknownCacheStrategyException($cacheStrategyClassName,
                $validStrategies + \array_values(self::CACHE_STRATEGIES));
        }

        $this->_cacheStrategy = new $cacheStrategyClassName($this->_storage);
    }

    /**
     * Returns true|false if the $implementerClassName implements interface $interfaceName
     * @param string $implementerClassName class name of the implementation class to test
     * @param string $interfaceName name of the interface that should be implemented
     * @return bool TRUE if the $implementerClassName implements $interfaceName, FALSE otherwise
     */
    private function _implementsInterface(string $implementerClassName, string $interfaceName) : bool {
        return \in_array($interfaceName, \class_implements($implementerClassName), TRUE);
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