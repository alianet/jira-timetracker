<?php

declare(strict_types=1);

namespace Tests\Composition;

use App\Composition\CompiledContainerCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tests\TestCase;

use function Safe\file_get_contents;
use function Safe\filemtime;
use function Safe\touch;
use function Safe\unserialize;

final class CompiledContainerCacheTest extends TestCase
{
    public function testBuildsAndCachesCompiledContainerOnCacheMiss(): void
    {
        $directory = $this->createTempDirectory();
        $cachePath = $directory . '/container.php';
        $resourcePath = $directory . '/services.php';
        $this->writeFile($directory, 'services.php', '<?php');
        $builds = 0;

        $container = new CompiledContainerCache($cachePath, true)->load(
            static function () use (&$builds, $resourcePath): ContainerBuilder {
                ++$builds;
                $container = new ContainerBuilder();
                $container->register('cached_service', \stdClass::class)->setPublic(true);
                $container->addResource(new FileResource($resourcePath));
                $container->compile();

                return $container;
            },
        );

        self::assertSame(1, $builds);
        self::assertInstanceOf(Container::class, $container);
        self::assertInstanceOf(\stdClass::class, $container->get('cached_service'));
        self::assertFileExists($cachePath);
        self::assertFileExists($cachePath . '.meta');

        $resources = unserialize(file_get_contents($cachePath . '.meta'));
        self::assertIsArray($resources);
        self::assertContainsEquals(new FileResource($resourcePath), $resources);
    }

    public function testLoadsCompiledContainerWithoutBuildingGraphOnCacheHit(): void
    {
        $cachePath = $this->createTempDirectory() . '/container.php';
        new CompiledContainerCache($cachePath, true)->load(static function (): ContainerBuilder {
            $container = new ContainerBuilder();
            $container->register('cached_service', \stdClass::class)->setPublic(true);
            $container->compile();

            return $container;
        });

        $container = new CompiledContainerCache($cachePath, true)->load(static function (): ContainerBuilder {
            self::fail('Container graph must not be rebuilt on a cache hit.');
        });

        self::assertInstanceOf(Container::class, $container);
        self::assertInstanceOf(\stdClass::class, $container->get('cached_service'));
    }

    public function testRebuildsDebugCacheWhenAResourceChanges(): void
    {
        $directory = $this->createTempDirectory();
        $cachePath = $directory . '/container.php';
        $resourcePath = $directory . '/services.php';
        $this->writeFile($directory, 'services.php', '<?php');
        $builds = 0;
        $build = static function () use (&$builds, $resourcePath): ContainerBuilder {
            ++$builds;
            $container = new ContainerBuilder();
            $container->addResource(new FileResource($resourcePath));
            $container->compile();

            return $container;
        };
        $cache = new CompiledContainerCache($cachePath, true);

        $cache->load($build);
        touch($resourcePath, filemtime($cachePath) + 1);
        $cache->load($build);

        self::assertSame(2, $builds);
    }

    public function testProductionLoadRequiresExplicitWarmup(): void
    {
        $cachePath = $this->createTempDirectory() . '/container.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer cache:container:warmup');

        new CompiledContainerCache($cachePath, false)->load(static function (): ContainerBuilder {
            self::fail('Production requests must not build the container graph.');
        });
    }

    public function testProductionLoadDoesNotCheckResources(): void
    {
        $directory = $this->createTempDirectory();
        $cachePath = $directory . '/container.php';
        $resourcePath = $directory . '/services.php';
        $this->writeFile($directory, 'services.php', '<?php');
        $cache = new CompiledContainerCache($cachePath, false);
        $cache->warmUp(static function () use ($resourcePath): ContainerBuilder {
            $container = new ContainerBuilder();
            $container->addResource(new FileResource($resourcePath));
            $container->compile();

            return $container;
        });
        touch($resourcePath, filemtime($cachePath) + 1);

        $container = $cache->load(static function (): ContainerBuilder {
            self::fail('Production requests must not scan resources or rebuild the container graph.');
        });

        self::assertInstanceOf(Container::class, $container);
    }

    public function testCreatesMissingCacheDirectory(): void
    {
        $directory = $this->createTempDirectory() . '/var/cache/container';
        $cachePath = $directory . '/container.php';

        new CompiledContainerCache($cachePath, false)->warmUp(static function (): ContainerBuilder {
            $container = new ContainerBuilder();
            $container->compile();

            return $container;
        });

        self::assertDirectoryExists($directory);
        self::assertFileExists($cachePath);
        self::assertFileExists($cachePath . '.lock');
    }

    public function testReportsClearErrorWhenCacheDirectoryCannotBeCreated(): void
    {
        $directory = $this->createTempDirectory();
        $this->writeFile($directory, 'blocked', 'not a directory');
        $cachePath = $directory . '/blocked/container.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Nie można zapisać cache kontenera w pliku "%s"', $cachePath));
        $this->expectExceptionMessage(sprintf('nie można utworzyć katalogu "%s"', dirname($cachePath)));

        new CompiledContainerCache($cachePath, false)->warmUp(static function (): ContainerBuilder {
            self::fail('Container graph must not be built when the cache directory cannot be created.');
        });
    }
}
