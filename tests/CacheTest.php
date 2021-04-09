<?php

declare(strict_types=1);

namespace Jasny\VarCache\Tests;

use Jasny\PHPUnit\CallbackMockTrait;
use Jasny\PHPUnit\ExpectWarningTrait;
use Jasny\VarCache\InvalidArgumentException;
use Jasny\VarCache\Cache;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\VarCache\Cache
 */
class CacheTest extends TestCase
{
    use CallbackMockTrait;
    use ExpectWarningTrait;

    protected Cache $cache;
    protected vfsStreamDirectory $root;

    public function setUp(): void
    {
        $past = strtotime('now - 1 minute');

        $structure = [
            'cache.' . md5('one') . '.php' => '<?php return 1;',
            'cache.' . md5('two') . '.php' => '<?php return 2;',
            'cache.' . md5('old') . '.php' => "<?php return time() < {$past} ? 1 : false;",
            'resolve' => [],
        ];

        $this->root = vfsStream::setup('cache', 0775, $structure);
        $this->cache = new Cache(vfsStream::url('cache'));
    }

    /**
     * @test
     */
    public function assertOpcacheIsEnabled()
    {
        $this->expectNotToPerformAssertions();

        if (!function_exists('opcache_get_status')) {
            $this->addWarning('opcache extension is missing');
        } elseif (\opcache_get_status() === false) {
            $this->addWarning('opcache is disabled');
        }
    }

    public function testHas()
    {
        $this->assertTrue($this->cache->has('one'));
        $this->assertTrue($this->cache->has('two'));
        $this->assertFalse($this->cache->has('foo'));

        // has() disregards the ttl
        $this->assertTrue($this->cache->has('old'));
    }

    public function testHasWithMissingDir()
    {
        $cache = new Cache(vfsStream::url('cache/missing'));

        $this->assertFalse($cache->has('one'));
    }


    public function testGet()
    {
        $this->assertEquals(1, $this->cache->get('one', null));
        $this->assertEquals(2, $this->cache->get('two', null));

        $this->assertEquals(42, $this->cache->get('foo', 42));
        $this->assertEquals(42, $this->cache->get('old', 42));
    }

    public function testGetWithMissingDir()
    {
        $cache = new Cache(vfsStream::url('cache/missing'));

        $this->assertEquals(42, $cache->get('one', 42));
    }


    public function testSet()
    {
        $this->assertTrue($this->cache->set('ten', 10));
        $this->assertFileExists(vfsStream::url('cache/cache.' . md5('ten') . '.php'));

        $this->assertEquals(10, $this->cache->get('ten'));
    }

    public function testSetWithMissingDir()
    {
        $cache = new Cache(vfsStream::url('cache/missing'));

        $this->expectWarningMessageMatches('/failed to open stream/');
        $this->assertFalse($cache->set('ten', 10));
    }

    public function testSetWithResource()
    {
        $resource = fopen(vfsStream::url('cache/cache.' . md5('one') . '.php'), 'r+');

        $this->expectWarningMessage('Failed to cache "foo": Type "resource" is not supported.');

        $this->assertFalse($this->cache->set('foo', $resource));
    }

    /**
     * @medium
     */
    public function testSetWithTtl()
    {
        $this->cache->set('ten', 10, 2);

        $this->assertEquals(10, $this->cache->get('ten', 42));

        sleep(2);
        $this->assertEquals(42, $this->cache->get('ten', 42));
    }

    public function ttlProvider()
    {
        $interval = new \DateInterval('PT2S');
        $interval->invert = true;

        return [
            'int' => [-2],
            'DateInterval' => [$interval],
        ];
    }

    /**
     * Testing with negative Ttls to keep it fast.
     * @dataProvider ttlProvider
     */
    public function testSetGetExpired($ttl)
    {
        $this->cache->set('ten', 10, $ttl);
        $this->assertEquals(42, $this->cache->get('ten', 42));
    }

    public function testSetWithInvalidTtl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ttl should be of type int or DateInterval, not string');

        $this->assertFalse($this->cache->set('ten', 10, 'foo'));
    }


    public function testDelete()
    {
        $this->assertTrue($this->cache->delete('foo'));
    }

    public function testDeleteWithMissingDir()
    {
        $cache = new Cache(vfsStream::url('cache/missing'));

        $this->assertTrue($cache->delete('ten'));
    }


    public function testClear()
    {
        $this->assertTrue($this->cache->clear());

        $this->assertFileDoesNotExist(vfsStream::url('cache/cache.' . md5('one') . '.php'));
        $this->assertFileDoesNotExist(vfsStream::url('cache/cache.' . md5('two') . '.php'));
        $this->assertFileDoesNotExist(vfsStream::url('cache/cache.' . md5('old') . '.php'));
    }

    public function testClearWithMissingDir()
    {
        $cache = new Cache(vfsStream::url('cache/missing'));

        $this->assertTrue($cache->clear());
    }


    public function keysProvider()
    {
        return [
            'array' => [['foo', 'bar']],
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

    /**
     * @noinspection PhpParamsInspection
     */
    public function testGetMultipleInvalidKeys()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->getMultiple('foo');
    }

    public function testSetMultiple()
    {
        $this->assertTrue($this->cache->setMultiple(['ten' => 10, 'twenty' => 20]));

        $this->assertFileExists(vfsStream::url('cache/cache.' . md5('ten') . '.php'));
        $this->assertFileExists(vfsStream::url('cache/cache.' . md5('twenty') . '.php'));

        $this->assertEquals(10, $this->cache->get('ten'));
        $this->assertEquals(20, $this->cache->get('twenty'));
    }

    public function testDeleteMultiple()
    {
        $this->assertTrue($this->cache->deleteMultiple(['two', 'three', 'old']));

        $this->assertFalse($this->cache->has('two'));
        $this->assertFalse($this->cache->has('three'));
        $this->assertFalse($this->cache->has('old'));

        $this->assertTrue($this->cache->has('one'));
    }


    public function testResolve()
    {
        $cache = new Cache(vfsStream::url('cache/resolve'));
        $closure = \Closure::fromCallable($this->createCallbackMock($this->once(), [], 42));

        $this->assertEquals(42, $cache->resolve($closure));

        /** @var vfsStreamDirectory $dir */
        $dir = $this->root->getChild('resolve');

        /** @var vfsStreamFile[] $files */
        $files = $dir->getChildren();
        $this->assertCount(1, $files);
        $this->assertInstanceOf(vfsStreamFile::class, $files[0]);
        $this->assertMatchesRegularExpression('/^cache\.[0-9abcdef]{32}\.php$/', $files[0]->getName());

        /** @noinspection PhpIncludeInspection */
        $value = include $files[0]->url();
        $this->assertEquals(42, $value);

        $this->assertEquals(42, $cache->resolve($closure)); // Closure shouldn't be called

        $files[0]->setContent('<?php return 99;');
        $this->assertEquals(99, $cache->resolve($closure)); // Make sure value comes from file
    }

    public function testResolveWithMissingDir()
    {
        $cache = new Cache(vfsStream::url('cache/missing'));

        $closure = \Closure::fromCallable($this->createCallbackMock($this->once(), [], 1));

        $this->expectWarningMessageMatches('/failed to open stream/');
        $this->assertEquals(1, $cache->resolve($closure));
    }

    public function testResolveWithResource()
    {
        $cache = new Cache(vfsStream::url('cache/resolve'));

        $resource1 = fopen(vfsStream::url('cache/cache.' . md5('one') . '.php'), 'r+');
        $resource2 = fopen(vfsStream::url('cache/cache.' . md5('two') . '.php'), 'r+');

        $closure = \Closure::fromCallable($this->createCallbackMock(
            $this->exactly(2),
            function(InvocationMocker $invoke) use($resource1, $resource2) {
                $invoke->willReturnOnConsecutiveCalls($resource1, $resource2);
            }
        ));

        $this->expectWarningMessage('Failed to cache stream resource: Type "resource" is not supported.');
        $this->assertSame($resource1, $cache->resolve($closure));

        /** @var vfsStreamDirectory $dir */
        $dir = $this->root->getChild('resolve');
        $this->assertCount(0, $dir->getChildren());

        $this->expectWarningMessage('Failed to cache stream resource: Type "resource" is not supported.');
        $this->assertSame($resource2, $cache->resolve($closure));
    }
}
