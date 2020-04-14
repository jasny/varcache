![jasny-banner](https://user-images.githubusercontent.com/100821/62123924-4c501c80-b2c9-11e9-9677-2ebc21d9b713.png)

VarCache
===

[![Build Status](https://travis-ci.org/jasny/varcache.svg?branch=master)](https://travis-ci.org/jasny/varcache)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/varcache/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/varcache/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/varcache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/varcache/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/varcache.svg)](https://packagist.org/packages/jasny/varcache)
[![Packagist License](https://img.shields.io/packagist/l/jasny/varcache.svg)](https://packagist.org/packages/jasny/varcache)

A cache implementation based on [500x faster caching] and [brick/varexporter].

[500x faster caching]: https://medium.com/@dylanwenzlau/500x-faster-caching-than-redis-memcache-apc-in-php-hhvm-dcd26e8447ad
[brick/varexporter]: https://github.com/brick/varexporter

Installation
---

    composer require jasny/varcache

Usage
---

### PSR-16

The `Cache` object implements PSR-16 simple cache.

```php
use Jasny\VarCache\Cache;

$cache = new Cache('path/to/cache/dir');

$service = $cache->get('service');

if ($service === null) {
    $service = new Service();

    // ... Initialize service

    $cache->set('service', $service);
}
```

_Caveat: Method `has()` disregards the ttl._

### Resolve

The `resolve` method takes a closure (without arguments). It will cache the return value of the function as PHP script.
On subsequent requests the closure isn't called, but the cached value is returned.

```php
use Jasny\VarCache\Cache;

$cache = new Cache('path/to/cache/dir');

$service = $cache->resolve(function() {
    $service = new Service();
    
    // ... Initialize service
    
    return $service;
});
```

_The `cache` function uses reflection to create a hash of the closure. Limited information about the closure is returned
through reflection. This means that changes to the code don't automatically result in a different hash._

### Null object

During development, you may not want to cache. `NoCache` is a null object that will not store anything.

```php
use Jasny\VarCache\NoCache;

$cache = new NoCache();

$service = $cache->resolve(function() {
    $service = new Service();
    
    // ... Initialize service
    
    return $service;
});
```
