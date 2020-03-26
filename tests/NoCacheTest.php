<?php

declare(strict_types=1);

namespace Jasny\VarCache\Tests;

use Jasny\PHPUnit\CallbackMockTrait;
use Jasny\VarCache\InvalidArgumentException;
use Jasny\VarCache\NoCache;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\VarCache\NoCache
 */
class NoCacheTest extends TestCase
{
    use CallbackMockTrait;

    protected NoCache $cache;

    public function setUp(): void
    {
        $this->cache = new NoCache();
    }

    public function testHas()
    {
        $this->assertFalse($this->cache->has('foo'));
    }

    public function testGet()
    {
        $this->assertEquals(42, $this->cache->get('foo', 42));
    }

    public function testSet()
    {
        $this->assertFalse($this->cache->set('foo', 42));
    }

    public function testDelete()
    {
        $this->assertTrue($this->cache->delete('foo'));
    }

    public function testClear()
    {
        $this->assertTrue($this->cache->clear());
    }


    public function keysProvider()
    {
        return [
            'array'    => [['foo', 'bar']],
            'iterator' => [new \ArrayIterator(['foo', 'bar'])],
        ];
    }

    /**
     * @dataProvider keysProvider
     */
    public function testGetMultiple($keys)
    {
        $this->assertEquals(['foo' => 42, 'bar' => 42], $this->cache->getMultiple($keys, 42));
    }

    public function testGetMultipleInvalidKeys()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->getMultiple('foo');
    }

    public function testSetMultiple()
    {
        $this->assertFalse($this->cache->setMultiple(['foo' => 1, 'bar' => 10]));
    }

    public function testDeleteMultiple()
    {
        $this->assertFalse($this->cache->deleteMultiple(['foo', 'bar']));
    }


    public function testResolve()
    {
        $closure = \Closure::fromCallable($this->createCallbackMock($this->once(), [], 42));

        $this->assertEquals(42, $this->cache->resolve($closure));
    }
}
