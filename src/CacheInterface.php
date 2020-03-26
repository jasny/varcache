<?php

declare(strict_types=1);

namespace Jasny\VarCache;

/**
 * PSR-16 cache interface + resolve method.
 */
interface CacheInterface extends \Psr\SimpleCache\CacheInterface
{
    /**
     * Cache the return value of the closure.
     * On subsequent requests the closure isn't called, but the cached value is returned instead.
     */
    public function resolve(\Closure $closure, $ttl = null);
}
