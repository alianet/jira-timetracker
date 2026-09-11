<?php

declare(strict_types=1);

namespace App\Composition;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

use function Safe\fclose;
use function Safe\flock;
use function Safe\fopen;
use function Safe\mkdir;

final class CompiledContainerCache
{
    public function __construct(
        private readonly string $cachePath,
        private readonly bool $debug,
    ) {}

    /** @param callable(): ContainerBuilder $buildContainer */
    public function load(callable $buildContainer): Container
    {
        $containerClass = $this->containerClass();

        if ($this->debug) {
            $cache = new ConfigCache($this->cachePath, true);
            if (!$cache->isFresh()) {
                $this->write($cache, $containerClass, $buildContainer, false);
            }
        } elseif (!is_file($this->cachePath)) {
            throw new \RuntimeException(sprintf(
                'Container cache file "%s" is missing. Run "composer cache:container:warmup" during deployment.',
                $this->cachePath,
            ));
        }

        require_once $this->cachePath;

        $container = new $containerClass();
        if (!$container instanceof Container) {
            throw new \LogicException(sprintf('Cached class "%s" must extend %s.', $containerClass, Container::class));
        }

        return $container;
    }

    /** @param callable(): ContainerBuilder $buildContainer */
    public function warmUp(callable $buildContainer): void
    {
        $this->write(
            new ConfigCache($this->cachePath, $this->debug),
            $this->containerClass(),
            $buildContainer,
            true,
        );
    }

    /** @param callable(): ContainerBuilder $buildContainer */
    private function write(
        ConfigCache $cache,
        string $containerClass,
        callable $buildContainer,
        bool $force,
    ): void {
        $this->createCacheDirectory();
        $lockPath = $this->cachePath . '.lock';
        try {
            $lock = fopen($lockPath, 'c');
        } catch (\Throwable $exception) {
            throw $this->writeException(sprintf('nie można utworzyć pliku blokady "%s"', $lockPath), $exception);
        }

        try {
            try {
                flock($lock, \LOCK_EX);
            } catch (\Throwable $exception) {
                throw $this->writeException(sprintf('nie można uzyskać blokady pliku "%s"', $lockPath), $exception);
            }

            clearstatcache(true, $cache->getPath());
            clearstatcache(true, $cache->getPath() . '.meta');
            if (!$force && $cache->isFresh()) {
                return;
            }

            $container = $buildContainer();
            $php = new PhpDumper($container)->dump([
                'class' => $containerClass,
                'debug' => $this->debug,
            ]);
            if (!is_string($php)) {
                throw new \LogicException('Compiled container must be dumped to a single PHP file.');
            }

            try {
                $cache->write($php, $this->debug ? $container->getResources() : null);
            } catch (\Throwable $exception) {
                throw $this->writeException($exception->getMessage(), $exception);
            }
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }

    private function createCacheDirectory(): void
    {
        $directory = dirname($this->cachePath);
        if (is_dir($directory)) {
            return;
        }

        try {
            @mkdir($directory, 0o777, true);
        } catch (\Throwable $exception) {
            if (!is_dir($directory)) {
                throw $this->writeException(sprintf('nie można utworzyć katalogu "%s"', $directory), $exception);
            }
        }
    }

    private function writeException(string $reason, ?\Throwable $previous = null): \RuntimeException
    {
        return new \RuntimeException(
            sprintf('Nie można zapisać cache kontenera w pliku "%s": %s.', $this->cachePath, rtrim($reason, '.')),
            previous: $previous,
        );
    }

    private function containerClass(): string
    {
        return 'CompiledContainer' . substr(hash('sha256', $this->cachePath), 0, 16);
    }
}
