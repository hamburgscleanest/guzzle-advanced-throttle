# hamburgscleanest/guzzle-advanced-throttle

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

A Guzzle middleware that throttles requests according to (multiple) defined rules.

It is also possible to define a caching strategy. For example, the response can be read from a cache when exceeding rate limits. The cached value can also be preferred to spare your rate limits (`force-cache`).

Using [wildcards](#wildcards) in hostnames is also supported.

## Install

Via Composer

``` bash
composer require hamburgscleanest/guzzle-advanced-throttle
```

## Usage

### General use

Let's say you wanted to implement the following rules:

> **20** requests every **1 seconds**
>
> **100** requests every **2 minutes**

----------

1. First, you have to define the rules in a `hamburgscleanest\GuzzleAdvancedThrottle\RequestLimitRuleset`:

``` php
$rules = new RequestLimitRuleset([
        'https://www.google.com' => [
            [
                'max_requests'     => 20,
                'request_interval' => 1
            ],
            [
                'max_requests'     => 100,
                'request_interval' => 120
            ]
        ]
    ]);
```

----------

2. Your handler stack might look like this:

``` php
 $stack = new HandlerStack();
 $stack->setHandler(new CurlHandler());
```

----------

3. Push `hamburgscleanest\GuzzleAdvancedThrottle\Middleware\ThrottleMiddleware` to the stack.

> It should always be the first middleware on the stack.

``` php
 $throttle = new ThrottleMiddleware($rules);

 // Invoke the middleware
 $stack->push($throttle());
 
 // OR: alternatively call the handle method directly
 $stack->push($throttle->handle());
```

----------

5. Pass the stack to the client

``` php
$client = new Client(['base_uri' => 'https://www.google.com', 'handler' => $stack]);
```

Either the `base_uri` has to be the same as the defined host in the rules array or you have to request absolute URLs for the middleware to have an effect.

``` php
// relative
$response = $client->get('test');

// absolute
$response = $client->get('https://www.google.com/test');
```

----------

### Caching

----------

#### Beforehand

Responses with an error status code `4xx` or `5xx` are not cached (even with `force-cache` enabled)!
Note: Currently, also redirect responses (`3xx`) are not cached.

----------

#### Available storage adapters

##### `array` (default)

This adapter works out of the box. However, it `does not persist` anything. This one only works within the same scope. It's set as a default because it doesn't need extra configuration.

The recommended adapter is the `laravel` one.

----------

##### `laravel` (Illuminate/Cache) - *recommended*

You need to provide a config (`Illuminate\Config\Repository`) for this adapter.

----------

##### `custom` (Implements `hamburgscleanest\GuzzleAdvancedThrottle\Cache\Interfaces\StorageInterface`)

When you create a new implementation, pass the class name to the `RequestLimitRuleset::create` method.
You'll also need to implement any sort of configuration parsing your instance needs.
Please see `LaravelAdapter` for an example.

###### Usage

``` php
$rules = new RequestLimitRuleset(
    [ ... ], 
    'force-cache', // caching strategy
    MyCustomAdapter::class // storage adapter
    );
    
$throttle = new ThrottleMiddleware($rules);

// Invoke the middleware
$stack->push($throttle());  
```

----------

#### Laravel Drivers

##### General settings

These values can be set for every adapter.

``` php
    'cache' => [
        'ttl' => 900, // How long should responses be cached for (in seconds)?
        'allow_empty' => true // When this is set to false, empty responses won't be cached.
    ]
```

----------

##### File

``` php
    'cache' => [
        'driver'  => 'file',
        'options' => [
            'path' => './cache'
        ],
        ...
    ]
```

----------

##### Redis

``` php
    'cache' => [
        'driver'  => 'redis',
        'options' => [
            'database' => [
                'cluster' => false,
                'default' => [
                    'host'     => '127.0.0.1',
                    'port'     => 6379,
                    'database' => 0,
                ],
            ]
        ],
        ...
    ]
```

----------

##### Memcached

``` php
    'cache' => [
        'driver'  => 'memcached',
        'options' => [
            'servers' => [
                [
                    'host'   => '127.0.0.1',
                    'port'   => 11211,
                    'weight' => 100,
                ],
            ]
        ],
        ...
    ]
```

----------

###### Pass the config repository in the constructor of RequestLimitRuleset

``` php
$rules = new RequestLimitRuleset(
    [ ... ], 
    'cache', // caching strategy
    'laravel', // storage adapter
    new Repository(require '../config/laravel-guzzle-limiter.php') // config repository
    );
```

----------

> The same adapter will be used to store the internal request timers.

----------

##### The adapters can be defined in the ruleset

``` php
$rules = new RequestLimitRuleset(
    [ ... ], 
    'cache', // caching strategy
    'array' // storage adapter
    );
```

----------

#### Without caching - `no-cache`

Just throttle the requests. The responses are not cached. Exceeding the rate limits results in a `429 - Too Many Requests` exception.

``` php
$rules = new RequestLimitRuleset(
    [ ... ], 
    'no-cache', // caching strategy
    'array' // storage adapter
    );
```

----------

#### With caching (default) - `cache`

The middleware tries to fall back to a cached value when the rate limits are exceeded before throwing a `429 - Too Many Requests` exception.

``` php
$rules = new RequestLimitRuleset(
    [ ... ], 
    'cache', // caching strategy
    'array' // storage adapter
    );
```

----------

#### With forced caching - `force-cache`

Always use cached responses when available to spare your rate limits.
As long as there is a response in the cache for the current request, it returns the cached response.
It will only actually send the request when no response is in the cache.
Otherwise, it throws a `429 - Too Many Requests` exception.

> You might want to disable the caching of empty responses with this option (see [General Driver Settings](https://github.com/hamburgscleanest/guzzle-advanced-throttle#laravel-drivers)).

``` php
$rules = new RequestLimitRuleset(
    [ ... ], 
    'force-cache', // caching strategy
    'array' // storage adapter
    );
```

----------

#### Custom caching strategy

The custom caching strategy must implement the `CacheStrategy` interface. It is advised to use the `Cacheable` abstraction to implement base functionality. For reference implementations, please check `ForceCache` and `Cache`.

To use the new caching strategy, you'll need to pass the fully qualified class name to `RequestLimitRuleset`.

##### Usage

```php
$rules = new RequestLimitRuleset([ ... ], 
                                MyCustomCacheStrategy::class, 
                                'array', 
                                new Repository(...));
                                
$throttle = new ThrottleMiddleware($rules);
...                                
```

----------

### Wildcards

If you want to define the same rules for multiple different hosts, you can use wildcards.
A possible use case can be subdomains:

``` php
$rules = new RequestLimitRuleset([
        'https://www.{subdomain}.mysite.com' => [
            [
                'max_requests'     => 50,
                'request_interval' => 2
            ]
        ]
    ]);
```

This `host` matches `https://www.en.mysite.com`, `https://www.de.mysite.com`, `https://www.fr.mysite.com`, etc.

----------

## Changes

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

----------

## Testing

``` bash
composer test
```

----------

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

----------

## Security

If you discover any security-related issues, please email [chroma91@gmail.com](mailto:chroma91@gmail.com) instead of using the issue tracker.

----------

## Credits

- [Timo Prüße][link-author]
- [Andre Biel][link-andre]
- [All Contributors][link-contributors]

----------

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/hamburgscleanest/guzzle-advanced-throttle.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/hamburgscleanest/guzzle-advanced-throttle/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/hamburgscleanest/guzzle-advanced-throttle.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/hamburgscleanest/guzzle-advanced-throttle.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/hamburgscleanest/guzzle-advanced-throttle.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/hamburgscleanest/guzzle-advanced-throttle
[link-travis]: https://app.travis-ci.com/github/hamburgscleanest/guzzle-advanced-throttle
[link-scrutinizer]: https://scrutinizer-ci.com/g/hamburgscleanest/guzzle-advanced-throttle/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/hamburgscleanest/guzzle-advanced-throttle
[link-downloads]: https://packagist.org/packages/hamburgscleanest/guzzle-advanced-throttle
[link-author]: https://github.com/Chroma91
[link-andre]: https://github.com/karllson
[link-contributors]: ../../contributors
