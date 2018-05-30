<?php

namespace hamburgscleanest\GuzzleAdvancedThrottle\Tests;

use GuzzleHttp\Psr7\Request;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Adapters\ArrayAdapter;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Adapters\LaravelAdapter;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Drivers\RedisDriver;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies\Cache;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies\ForceCache;
use hamburgscleanest\GuzzleAdvancedThrottle\Cache\Strategies\NoCache;
use hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\HostNotDefinedException;
use hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\LaravelCacheConfigNotSetException;
use hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownCacheStrategyException;
use hamburgscleanest\GuzzleAdvancedThrottle\Exceptions\UnknownStorageAdapterException;
use hamburgscleanest\GuzzleAdvancedThrottle\RequestLimitRuleset;
use hamburgscleanest\GuzzleAdvancedThrottle\TimeKeeper;
use PHPUnit\Framework\TestCase;

/**
 * Class RequestLimitRulesetTest
 * @package hamburgscleanest\GuzzleAdvancedThrottle\Tests
 */
class RequestLimitRulesetTest extends TestCase
{

    /** @test */
    public function can_be_created_statically() : void
    {
        $requestLimitRuleset = RequestLimitRuleset::create([]);

        static::assertInstanceOf(RequestLimitRuleset::class, $requestLimitRuleset);
    }

    /** @test */
    public function throws_unknown_cache_strategy_exception() : void
    {
        $this->expectException(UnknownCacheStrategyException::class);
        RequestLimitRuleset::create([], TimeKeeper::class);
    }

    /** @test */
    public function verify_exception_message_for_unknown_cache_strategy_exception() : void
    {
        try
        {
            RequestLimitRuleset::create([], TimeKeeper::class);
            $this->fail("Should have thrown " . UnknownCacheStrategyException::class);
        }
        catch (UnknownCacheStrategyException $e)
        {
            // PHPUnit doesn't have a good way to make sure an exception message contains parts of strings,
            // and I'm not creating a RegExp for this
            $msg = $e->getMessage();
            $this->assertThat($msg, self::stringContains((string) TimeKeeper::class));
            $this->assertThat($msg, self::stringContains((string) NoCache::class));
            $this->assertThat($msg, self::stringContains((string) Cache::class));
            $this->assertThat($msg, self::stringContains((string) ForceCache::class));
        }
    }

    /** @test */
    public function no_exception_on_custom_cache_strategy() : void
    {
        $this->assertInstanceOf(RequestLimitRuleset::class,
            RequestLimitRuleset::create([], DummyCacheStrategy::class));
    }

    /** @test */
    public function throws_unknown_storage_adapter_exception_on_invalid_adapter() : void
    {
        $this->expectException(UnknownStorageAdapterException::class);

        RequestLimitRuleset::create([], 'no-cache', RedisDriver::class);
    }

    /** @test */
    public function no_exception_on_valid_adapter() : void
    {
        $this->assertInstanceOf(RequestLimitRuleset::class,
            RequestLimitRuleset::create([], 'no-cache', ArrayAdapter::class));
    }

    /** @test */
    public function no_unknown_storage_adapter_exception_on_old_laravel_adapter_usage() : void
    {
        $this->expectException(LaravelCacheConfigNotSetException::class);
        RequestLimitRuleset::create([], 'no-cache', 'laravel');
    }

    /** @test */
    public function throws_unknown_storage_adapter_exception_on_unknown_adapter_usage() : void
    {
        try
        {
            RequestLimitRuleset::create([], 'no-cache', TimeKeeper::class);
            $this->fail('Should have thrown an exception');
        }
        catch (UnknownStorageAdapterException $e)
        {
            // PHPUnit doesn't have a good way to make sure an exception message contains parts of strings,
            // and I'm not creating a RegExp for this
            $msg = $e->getMessage();
            $this->assertThat($msg, self::stringContains((string) ArrayAdapter::class));
            $this->assertThat($msg, self::stringContains((string) LaravelAdapter::class));
        }
    }

    /** @test */
    public function no_unknown_storage_adapter_exception_on_custom_adapter_usage() : void
    {
        $this->assertInstanceOf(RequestLimitRuleset::class,
            RequestLimitRuleset::create([], 'no-cache', DummyStorageAdapter::class));
    }

    /** @test
     * @throws \Exception
     */
    public function ruleset_contains_the_correct_request_limit_group() : void
    {
        $host = 'http://www.test.com';
        $interval = 33;
        $ruleset = [
            $host => [
                [
                    'max_requests'     => 0,
                    'request_interval' => $interval
                ]
            ]
        ];

        $requestLimitRuleset = RequestLimitRuleset::create($ruleset);
        $requestLimitGroup = $requestLimitRuleset->getRequestLimitGroup();
        $requestLimitGroup->canRequest(new Request('GET', $host . '/check'));

        static::assertEquals($interval, $requestLimitGroup->getRetryAfter());
    }

    /** @test
     * @throws \Exception
     */
    public function host_for_rules_has_to_be_defined() : void
    {
        $ruleset = new RequestLimitRuleset([
            [
                [
                    'max_requests' => 1
                ]
            ]
        ]);

        $this->expectException(HostNotDefinedException::class);
        $ruleset->getRequestLimitGroup();
    }
}