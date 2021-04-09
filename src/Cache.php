<?php

declare(strict_types=1);

namespace Jasny\VarCache;

use Brick\VarExporter\ExportException;
use Brick\VarExporter\VarExporter;
use Improved as i;
use Improved\IteratorPipeline\Pipeline;

/**
 * Export value and store as PHP script.
 */
class Cache implements CacheInterface
{
    protected string $dir;

    /**
     * Class constructor
     *
     * @param string $dir
     */
    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
    }

    /**
     * @inheritdoc
     */
    public function has($key)
    {
        $file = $this->getFile($key);

        return self::opcacheIsCached($file) || \file_exists($file);
    }

    /**
     * @inheritdoc
     */
    public function get($key, $default = null)
    {
        $file = $this->getFile($key);

        if (!self::opcacheIsCached($file) && !\file_exists($file)) {
            return $default;
        }

        unset($key);

        /** @noinspection PhpIncludeInspection */
        $value = include $file;

        return $value !== false ? $value : $default;
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value, $ttl = null)
    {
        $file = $this->getFile($key);

        try {
            $script = $this->createScript($value, $this->ttlToTimestamp($ttl));
        } catch (ExportException $exception) {
            trigger_error("Failed to cache \"$key\": " . $exception->getMessage(), E_USER_WARNING);
            return false;
        }

        return (bool)file_put_contents($file, $script);
    }

    /**
     * @inheritdoc
     */
    public function delete($key)
    {
        $file = $this->getFile($key);

        self::opcacheInvalidate($file);

        return \file_exists($file) ? \unlink($file) : true;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        if (!file_exists($this->dir)) {
            return true;
        }

        $files = scandir($this->dir);

        if ($files === false) {
            return false; // @codeCoverageIgnore
        }

        return Pipeline::with($files)
            ->filter(fn($filename) => fnmatch('cache.*.php', $filename))
            ->map(fn($filename) => $this->dir . '/' . $filename)
            ->apply(fn($file) => self::opcacheInvalidate($file))
            ->map(fn($file) => \unlink($file))
            ->reduce(fn($success, $ret) => $success && $ret, true);
    }

    /**
     * @inheritdoc
     */
    public function getMultiple($keys, $default = null)
    {
        i\type_check($keys, 'iterable', new InvalidArgumentException());

        return Pipeline::with($keys)
            ->flip()
            ->map(fn($_, $key) => $this->get($key, $default))
            ->toArray();
    }

    /**
     * @inheritdoc
     */
    public function setMultiple($values, $ttl = null)
    {
        i\type_check($values, 'iterable', new InvalidArgumentException());

        return Pipeline::with($values)
            ->map(fn($value, $key) => $this->set($key, $value, $ttl))
            ->reduce(fn($success, $ret) => $success && $ret, true);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        i\type_check($keys, 'iterable', new InvalidArgumentException());

        return Pipeline::with($keys)
            ->map(fn($key) => $this->delete($key))
            ->reduce(fn($success, $ret) => $success && $ret, true);
    }


    /**
     * @inheritdoc
     */
    public function resolve(\Closure $closure, $ttl = null)
    {
        $key = (string)(new \ReflectionFunction($closure));
        $file = $this->getFile($key);

        if (self::opcacheIsCached($file) || \file_exists($file)) {
            /** @noinspection PhpIncludeInspection */
            return include $file;
        }

        $value = $closure();

        try {
            $script = $this->createScript($value, $this->ttlToTimestamp($ttl));
            \file_put_contents($file, $script);
        } catch (ExportException $exception) {
            $type = i\type_describe($value);
            \trigger_error("Failed to cache $type: " . $exception->getMessage(), E_USER_WARNING);
        }

        return $value;
    }


    /**
     * Get a filename for a key.
     *
     * @param string $key
     * @return string
     */
    protected function getFile(string $key): string
    {
        return $this->dir . '/cache.' . md5($key) . '.php';
    }

    /**
     * Create a PHP script returning the cached value
     *
     * @param mixed $value
     * @param int|null $ttl
     * @return string
     * @throws ExportException
     */
    protected function createScript($value, ?int $ttl): string
    {
        $code = VarExporter::export($value);

        return $ttl !== null
            ? "<?php return \\time() < {$ttl} ? {$code} : false;"
            : "<?php return {$code};";
    }

    /**
     * Convert TTL to epoch timestamp
     *
     * @param null|int|\DateInterval $ttl
     * @return int|null
     * @throws InvalidArgumentException
     */
    protected function ttlToTimestamp($ttl): ?int
    {
        /** @var mixed $ttl */
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl)) {
            return time() + $ttl;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTime())->add($ttl)->getTimestamp();
        }

        $type = i\type_describe($ttl);
        throw new InvalidArgumentException("ttl should be of type int or DateInterval, not $type");
    }

    /**
     * Wrapper for opcache_is_script_cached.
     *
     * @param string $file
     * @return bool
     */
    protected static function opcacheIsCached(string $file): bool
    {
        return function_exists('opcache_is_script_cached')
            ? \opcache_is_script_cached($file)
            : false;
    }

    /**
     * Wrapper for opcache_invalidate.
     *
     * @param string $file
     * @return bool
     */
    protected static function opcacheInvalidate(string $file): bool
    {
        return function_exists('opcache_invalidate')
            ? \opcache_invalidate($file, true)
            : false;
    }
}
