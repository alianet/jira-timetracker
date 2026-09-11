<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LayerDependenciesTest extends TestCase
{
    private const array BUSINESS_MODULES = ['Reporting', 'TimeTracking'];

    public function testLegacyTopLevelCataloguesAreGone(): void
    {
        $allowed = ['Composition', 'Identity', 'Kernel', 'Reporting', 'Shared', 'TimeTracking'];
        $directories = glob($this->root() . '/src/*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            self::assertContains(basename($directory), $allowed, "Unexpected top-level source directory {$directory}");
        }
    }

    public function testDomainHasNoOutwardFrameworkOrAdapterDependencies(): void
    {
        foreach ($this->phpFiles('src/*/Domain') as $file) {
            $imports = $this->imports($file);
            foreach ($imports as $import) {
                self::assertDoesNotMatchRegularExpression(
                    '/^(?:App\\\\[^\\\\]+\\\\(?:Infrastructure|Presentation)|Twig\\\\|Symfony\\\\|.*(?:Jira|Atlassian))/',
                    $import,
                    "Forbidden domain dependency {$import} in {$file}",
                );
            }
        }
    }

    public function testApplicationUsesPortsInsteadOfAdaptersOrPresentationTypes(): void
    {
        foreach ($this->phpFiles('src/*/Application') as $file) {
            foreach ($this->imports($file) as $import) {
                self::assertDoesNotMatchRegularExpression(
                    '/^(?:App\\\\[^\\\\]+\\\\(?:Infrastructure|Presentation)|Twig\\\\|Symfony\\\\.*(?:Http|Request|Response)|.*\\\\Infrastructure\\\\.*(?:Jira|Atlassian))/',
                    $import,
                    "Forbidden application dependency {$import} in {$file}",
                );
            }
        }
    }

    public function testApplicationAndDomainClassLikeNamesDoNotExposeProviders(): void
    {
        $files = [
            ...$this->phpFiles('src/*/Application'),
            ...$this->phpFiles('src/*/Domain'),
        ];
        self::assertNotEmpty($files, 'No Application or Domain sources found — the guard would pass vacuously.');

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all('/\b(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $matches);
            foreach ($matches[1] as $name) {
                self::assertDoesNotMatchRegularExpression(
                    '/(?:Jira|Atlassian)/i',
                    $name,
                    "Provider name exposed by {$name} in {$file}",
                );
            }
        }
    }

    /** Identity is a technical feature module, not a composition root. */
    public function testIdentityDoesNotImportReportingOrTimeTrackingTypes(): void
    {
        $files = $this->phpFiles('src/Identity');
        self::assertNotEmpty($files, 'No Identity sources found — the guard would pass vacuously.');

        foreach ($files as $file) {
            foreach ($this->imports($file) as $import) {
                self::assertDoesNotMatchRegularExpression(
                    '/^\\\\?App\\\\(?:Reporting|TimeTracking)\\\\/',
                    $import,
                    "Identity must not depend on {$import} in {$file}",
                );
            }
            self::assertDoesNotMatchRegularExpression(
                '/\\\\App\\\\(?:Reporting|TimeTracking)\\\\/',
                (string) file_get_contents($file),
                "Identity must not reference Reporting or TimeTracking by fully qualified name in {$file}",
            );
        }
    }

    public function testKernelDoesNotImportBusinessModuleTypes(): void
    {
        $files = $this->phpFiles('src/Kernel');
        self::assertNotEmpty($files, 'No Kernel sources found — the guard would pass vacuously.');

        foreach ($files as $file) {
            foreach ($this->imports($file) as $import) {
                self::assertDoesNotMatchRegularExpression(
                    '/^\\\\?App\\\\(?:Identity|Reporting|TimeTracking)\\\\/',
                    $import,
                    "Kernel must not depend on business type {$import} in {$file}",
                );
            }
            self::assertDoesNotMatchRegularExpression(
                '/\\\\App\\\\(?:Identity|Reporting|TimeTracking)\\\\/',
                (string) file_get_contents($file),
                "Kernel must not reference a business module by fully qualified name in {$file}",
            );
        }
    }

    public function testPresentationDoesNotConstructInfrastructureAdapters(): void
    {
        foreach ($this->phpFiles('src/*/Presentation') as $file) {
            $source = (string) file_get_contents($file);
            $adapterNames = [];
            foreach ($this->imports($file) as $import) {
                if (str_contains($import, '\\Infrastructure\\')) {
                    $adapterNames[] = substr($import, (int) strrpos($import, '\\') + 1);
                }
            }
            foreach ($adapterNames as $adapterName) {
                self::assertDoesNotMatchRegularExpression('/new\\s+' . preg_quote($adapterName, '/') . '\\s*\\(/', $source, "Presentation creates {$adapterName} in {$file}");
            }
            self::assertDoesNotMatchRegularExpression('/new\\s+\\\\?App\\\\[^;\s(]+\\\\Infrastructure\\\\/', $source, "Presentation creates an infrastructure adapter in {$file}");
        }
    }

    public function testPresentationClassesDoNotImplementApplicationOrDomainPorts(): void
    {
        $files = $this->phpFiles('src/*/Presentation');
        self::assertNotEmpty($files, 'No Presentation sources found — the guard would pass vacuously.');

        foreach ($files as $file) {
            $relative = substr($file, strlen($this->root() . '/src/'), -strlen('.php'));
            $class = 'App\\' . str_replace('/', '\\', $relative);
            if (!class_exists($class)) {
                continue;
            }

            foreach (class_implements($class) ?: [] as $interface) {
                $interfaceFile = new \ReflectionClass($interface)->getFileName();
                if ($interfaceFile === false) {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression(
                    '#/src/[^/]+/(?:Application|Domain/Port)/#',
                    str_replace('\\', '/', $interfaceFile),
                    "Presentation class {$class} implements business port {$interface}",
                );
            }
        }
    }

    /** @return iterable<string, array{class-string, class-string}> */
    public static function adaptersAndPorts(): iterable
    {
        yield 'report source' => [\App\Reporting\Infrastructure\Jira\JiraWorklogReportSource::class, \App\Reporting\Application\Port\WorklogReportSource::class];
        yield 'CSV exporter' => [\App\Reporting\Infrastructure\Csv\CsvReportExporter::class, \App\Reporting\Application\Port\ReportExporter::class];
        yield 'holiday calendar' => [\App\Reporting\Infrastructure\Calendar\UmulmrumHolidayCalendar::class, \App\Reporting\Domain\Port\HolidayCalendar::class];
        yield 'worklog gateway' => [\App\TimeTracking\Infrastructure\Jira\JiraWorklogRepository::class, \App\TimeTracking\Application\Port\WorklogGateway::class];
        yield 'issue directory' => [\App\TimeTracking\Infrastructure\Jira\JiraIssueDirectory::class, \App\TimeTracking\Application\Query\IssueDirectory::class];
        yield 'user directory' => [\App\Identity\Infrastructure\Atlassian\AtlassianUserDirectory::class, \App\Identity\Application\Query\UserDirectory::class];
        yield 'OAuth gateway' => [\App\Identity\Infrastructure\Atlassian\AtlassianOAuth::class, \App\Identity\Application\Authentication\AuthorizationGateway::class];
        yield 'personal access gateway' => [\App\Identity\Infrastructure\Atlassian\AtlassianPersonalAccess::class, \App\Identity\Application\Authentication\AuthorizationGateway::class];
        yield 'OAuth connection provider' => [\App\Identity\Infrastructure\Atlassian\AtlassianOAuth::class, \App\Identity\Application\Authentication\ConnectionProvider::class];
        yield 'personal access connection provider' => [\App\Identity\Infrastructure\Atlassian\AtlassianPersonalAccess::class, \App\Identity\Application\Authentication\ConnectionProvider::class];
        yield 'user directory transport' => [\App\Identity\Infrastructure\Atlassian\AtlassianHttpClient::class, \App\Identity\Infrastructure\Atlassian\AtlassianTransport::class];
        yield 'session token store' => [\App\Identity\Infrastructure\Session\SessionAccessTokenStore::class, \App\Identity\Application\Authentication\AccessTokenStore::class];
        yield 'native session security' => [\App\Identity\Infrastructure\Session\NativeSessionSecurity::class, \App\Identity\Presentation\Http\SessionSecurity::class];
    }

    /** @param class-string $adapter @param class-string $port */
    #[DataProvider('adaptersAndPorts')]
    public function testAdaptersImplementBusinessPorts(string $adapter, string $port): void
    {
        self::assertTrue(is_subclass_of($adapter, $port), "{$adapter} must implement {$port}");
    }

    public function testBusinessModuleDependencyGraphHasNoCycles(): void
    {
        $edges = array_fill_keys(self::BUSINESS_MODULES, []);
        foreach ($this->phpFiles('src') as $file) {
            $source = $this->moduleFromPath($file);
            if ($source === null) {
                continue;
            }
            foreach ($this->imports($file) as $import) {
                if (preg_match('/^App\\\\(' . implode('|', self::BUSINESS_MODULES) . ')\\\\/', $import, $match) === 1 && $match[1] !== $source) {
                    $edges[$source][$match[1]] = true;
                }
            }
        }

        foreach (self::BUSINESS_MODULES as $start) {
            $this->assertNoPathBackTo($start, $start, $edges, []);
        }

        self::assertTrue(true);
    }

    public function testTestPathsMirrorAtLeastOneTestedSourceLayer(): void
    {
        foreach ($this->phpFiles('tests') as $file) {
            $relative = substr($file, strlen($this->root() . '/tests/'));
            if ($relative === 'TestCase.php' || str_starts_with($relative, 'Architecture/')) {
                continue;
            }

            $expectedNamespace = 'App\\' . str_replace('/', '\\', dirname($relative)) . '\\';
            $matchingImports = array_filter(
                $this->imports($file),
                static fn(string $import): bool => str_starts_with($import, $expectedNamespace),
            );

            self::assertNotEmpty(
                $matchingImports,
                "Test {$relative} must live beside the path of at least one tested source type ({$expectedNamespace}).",
            );
        }
    }

    /** @return list<string> */
    private function phpFiles(string $path): array
    {
        $files = glob($this->root() . '/' . $path . '/*.php') ?: [];
        $directories = glob($this->root() . '/' . $path . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($directories as $directory) {
            array_push($files, ...$this->phpFiles(substr($directory, strlen($this->root()) + 1)));
        }
        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function imports(string $file): array
    {
        $source = (string) file_get_contents($file);
        preg_match_all('/^use\\s+([^;]+);/m', $source, $matches);

        return array_values(array_filter($matches[1], static fn(string $import): bool => !str_starts_with($import, 'function ')));
    }

    private function moduleFromPath(string $file): ?string
    {
        foreach (self::BUSINESS_MODULES as $module) {
            if (str_contains($file, '/src/' . $module . '/')) {
                return $module;
            }
        }

        return null;
    }

    /** @param array<string, array<string, bool>> $edges @param array<string, bool> $visited */
    private function assertNoPathBackTo(string $start, string $current, array $edges, array $visited): void
    {
        $visited[$current] = true;
        foreach (array_keys($edges[$current]) as $next) {
            self::assertFalse($next === $start, "Cyclic module dependency reaches {$start} through {$current}");
            if (!isset($visited[$next])) {
                $this->assertNoPathBackTo($start, $next, $edges, $visited);
            }
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
