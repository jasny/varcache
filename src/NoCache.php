<?php

declare(strict_types=1);

namespace Jasny\VarCache;

use Improved as i;

/**
 * Null object.
 */
class NoCache implements CacheInterface
{
    public function has($key)
    {
        return false;
    }

    public function get($key, $default = null)
    {
        return $default;
    }

    public function set($key, $value, $ttl = null)
    {
        return false;
    }

    public function delete($key)
    {
        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        i\type_check($keys, 'iterable', new InvalidArgumentException());

        return array_fill_keys(i\iterable_to_array($keys), $default);
    }

    public function setMultiple($values, $ttl = null)
    {
        return false;
    }

    public function deleteMultiple($keys)
    {
        return false;
    }

    public function clear()
    {
        return true;
    }

    public function resolve(\Closure $closure, $ttl = null)
    {
        return $closure();
    }
}
