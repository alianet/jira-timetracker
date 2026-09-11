<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Composition;

use App\Composition\ContainerFactory;
use App\Identity\Application\Authentication\AccountType;
use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Application\Authentication\Connection;
use App\Kernel\Config\Config;
use App\Kernel\Presentation\Http\FrontController;
use App\Kernel\Session\Session;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Translation\Translator;
use Tests\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use function Safe\file_get_contents;
use function Safe\filemtime;
use function Safe\touch;
use function Safe\unserialize;

final class ContainerFactoryTest extends TestCase
{
    public function testBuildsDefinitionsSeparatelyFromRuntimeServiceHydration(): void
    {
        $config = $this->createConfig([
            'REPORT_EXPORT_ENABLED' => 'false',
            'DAILY_HOURS_LIMIT' => '8',
            'APP_TIMEZONE' => 'Europe/Warsaw',
            'HOLIDAY_COUNTRY' => 'Poland',
        ]);
        $sessionData = [];
        $session = new Session($sessionData);
        $translator = new Translator('pl');
        $twig = new Environment(new ArrayLoader());
        $logger = new NullLogger();
        $factory = new ContainerFactory($this->createTempDirectory(), true);

        $container = $factory->buildDefinitions();

        self::assertTrue($container->getDefinition(Config::class)->isSynthetic());
        self::assertFalse($container->initialized(Config::class));

        $services = $factory->runtimeServices(
            $config,
            $session,
            $translator,
            $twig,
            $logger,
            AccountType::Individual,
        );
        $factory->hydrate($container, $services);

        self::assertSame($config, $container->get(Config::class));
        self::assertSame($session, $container->get(Session::class));
        self::assertSame($translator, $container->get(Translator::class));
        self::assertSame($twig, $container->get(Environment::class));
        self::assertSame($logger, $container->get(\Psr\Log\LoggerInterface::class));
        self::assertSame(AccountType::Individual, $container->get(AccountType::class));
    }

    public function testBuildsIndividualAuthenticationGraphWithoutEmbeddingRuntimeConfiguration(): void
    {
        $config = $this->createConfig([
            'JIRA_URL' => 'https://jira.example',
            'ATLASSIAN_EMAIL' => 'user@example.com',
            'ATLASSIAN_API_TOKEN' => 'individual-secret',
            'REPORT_EXPORT_ENABLED' => 'false',
            'DAILY_HOURS_LIMIT' => '8',
            'APP_TIMEZONE' => 'Europe/Warsaw',
            'HOLIDAY_COUNTRY' => 'Poland',
        ]);
        $container = $this->createContainer($config, AccountType::Individual);

        self::assertInstanceOf(FrontController::class, $container->get(FrontController::class));
        self::assertFalse($container->initialized(Connection::class));

        $connection = $container->get(Connection::class);

        self::assertSame(AuthenticationMode::PersonalToken, $connection->mode);
        self::assertTrue($connection->authenticated);
        self::assertSame($connection, $container->get(Connection::class));
        self::assertRuntimeConfigurationIsSynthetic($container, $config, AccountType::Individual);
        $this->assertDefinitionsDoNotContain($config, AccountType::Individual, 'individual-secret');
    }

    public function testBuildsCompanyAuthenticationGraphWithoutEmbeddingRuntimeConfiguration(): void
    {
        $config = $this->createConfig([
            'JIRA_URL' => 'https://jira.example',
            'ATLASSIAN_CLIENT_ID' => 'company-client-id',
            'ATLASSIAN_CLIENT_SECRET' => 'company-client-secret',
            'ATLASSIAN_REDIRECT_URI' => 'https://app.example/oauth/callback',
            'SESSION_ENCRYPTION_KEY' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'REPORT_EXPORT_ENABLED' => 'false',
            'DAILY_HOURS_LIMIT' => '8',
            'APP_TIMEZONE' => 'Europe/Warsaw',
            'HOLIDAY_COUNTRY' => 'Poland',
        ]);
        $container = $this->createContainer($config, AccountType::Company);

        self::assertInstanceOf(FrontController::class, $container->get(FrontController::class));
        self::assertFalse($container->initialized(Connection::class));

        $connection = $container->get(Connection::class);

        self::assertSame(AuthenticationMode::InteractiveOAuth, $connection->mode);
        self::assertFalse($connection->authenticated);
        self::assertSame($connection, $container->get(Connection::class));
        self::assertRuntimeConfigurationIsSynthetic($container, $config, AccountType::Company);
        $this->assertDefinitionsDoNotContain(
            $config,
            AccountType::Company,
            'company-client-secret',
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        );
    }

    public function testCreatesCompanySessionCipherOnlyWhenTheDependentServiceIsRetrieved(): void
    {
        $config = $this->createConfig([
            'JIRA_URL' => 'https://jira.example',
            'ATLASSIAN_CLIENT_ID' => 'company-client-id',
            'ATLASSIAN_CLIENT_SECRET' => 'company-client-secret',
            'ATLASSIAN_REDIRECT_URI' => 'https://app.example/oauth/callback',
            'SESSION_ENCRYPTION_KEY' => 'AA',
            'REPORT_EXPORT_ENABLED' => 'false',
            'DAILY_HOURS_LIMIT' => '8',
            'APP_TIMEZONE' => 'Europe/Warsaw',
            'HOLIDAY_COUNTRY' => 'Poland',
        ]);

        $container = $this->createContainer($config, AccountType::Company);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SESSION_ENCRYPTION_KEY musi kodować dokładnie 32 bajty.');

        $container->get(Connection::class);
    }

    public function testReusesCompiledCacheAndHydratesEveryContainerWithCurrentRuntimeServices(): void
    {
        $directory = $this->createTempDirectory();
        $cachePath = $directory . '/var/cache/container/AppContainer.php';
        $factory = new ContainerFactory($directory . '/var/cache/container', false);
        $factory->warmUp();
        $firstConfig = $this->individualConfig('first-secret');
        $firstSessionData = [];
        $firstSession = new Session($firstSessionData);
        $firstTranslator = new Translator('pl');
        $firstTwig = new Environment(new ArrayLoader());
        $firstLogger = new NullLogger();

        $first = $factory->create(
            $firstConfig,
            $firstSession,
            $firstTranslator,
            $firstTwig,
            $firstLogger,
            AccountType::Individual,
        );

        self::assertFileExists($cachePath);
        touch($cachePath, 1);
        clearstatcache(true, $cachePath);

        $secondConfig = $this->individualConfig('second-secret');
        $secondSessionData = [];
        $secondSession = new Session($secondSessionData);
        $secondTranslator = new Translator('en');
        $secondTwig = new Environment(new ArrayLoader());
        $secondLogger = new NullLogger();
        $second = $factory->create(
            $secondConfig,
            $secondSession,
            $secondTranslator,
            $secondTwig,
            $secondLogger,
            AccountType::Individual,
        );

        clearstatcache(true, $cachePath);
        self::assertSame(1, filemtime($cachePath));
        self::assertNotSame($first, $second);
        self::assertSame($firstConfig, $first->get(Config::class));
        self::assertSame($firstSession, $first->get(Session::class));
        self::assertSame($secondConfig, $second->get(Config::class));
        self::assertSame($secondSession, $second->get(Session::class));
        self::assertSame($secondTranslator, $second->get(Translator::class));
        self::assertSame($secondTwig, $second->get(Environment::class));
        self::assertSame($secondLogger, $second->get(\Psr\Log\LoggerInterface::class));
        self::assertFalse($second->initialized(Connection::class));
    }

    public function testCompiledCacheDoesNotContainRuntimeSecrets(): void
    {
        $directory = $this->createTempDirectory();
        $apiToken = 'api-token-that-must-not-be-cached';
        $clientSecret = 'client-secret-that-must-not-be-cached';
        $encryptionKey = 'ZW5jcnlwdGlvbi1rZXktdGhhdC1tdXN0LW5vdC1sZWFrISE';
        $config = $this->createConfig([
            'JIRA_URL' => 'https://jira.example',
            'ATLASSIAN_API_TOKEN' => $apiToken,
            'ATLASSIAN_CLIENT_ID' => 'company-client-id',
            'ATLASSIAN_CLIENT_SECRET' => $clientSecret,
            'ATLASSIAN_REDIRECT_URI' => 'https://app.example/oauth/callback',
            'SESSION_ENCRYPTION_KEY' => $encryptionKey,
            'REPORT_EXPORT_ENABLED' => 'false',
            'DAILY_HOURS_LIMIT' => '8',
            'APP_TIMEZONE' => 'Europe/Warsaw',
            'HOLIDAY_COUNTRY' => 'Poland',
        ]);

        $this->createContainerInDirectory($directory, $config, AccountType::Company);

        $compiledPhp = file_get_contents($directory . '/AppContainer.php');
        self::assertStringNotContainsString($apiToken, $compiledPhp);
        self::assertStringNotContainsString($clientSecret, $compiledPhp);
        self::assertStringNotContainsString($encryptionKey, $compiledPhp);
    }

    public function testDebugCacheTracksServiceConfigurationAndSourceFiles(): void
    {
        $directory = $this->createTempDirectory();
        $factory = new ContainerFactory($directory, true);

        $factory->warmUp();

        $resources = unserialize(file_get_contents($directory . '/AppContainer.php.meta'));
        self::assertIsArray($resources);
        self::assertContainsEquals(new FileResource(dirname(__DIR__, 2) . '/config/services.php'), $resources);
        self::assertContainsEquals(new DirectoryResource(dirname(__DIR__, 2) . '/src', '/\.php$/'), $resources);
        self::assertContainsEquals(new FileResource(dirname(__DIR__, 2) . '/src/Composition/ContainerFactory.php'), $resources);
    }

    private function createContainer(Config $config, AccountType $accountType): Container
    {
        return $this->createContainerInDirectory($this->createTempDirectory(), $config, $accountType);
    }

    private function createContainerInDirectory(
        string $directory,
        Config $config,
        AccountType $accountType,
    ): Container {
        $sessionData = [];

        $factory = new ContainerFactory($directory, false);
        $factory->warmUp();

        return $factory->create(
            $config,
            new Session($sessionData),
            new Translator('pl'),
            new Environment(new ArrayLoader()),
            new NullLogger(),
            $accountType,
        );
    }

    private static function assertRuntimeConfigurationIsSynthetic(
        Container $container,
        Config $config,
        AccountType $accountType,
    ): void {
        self::assertSame($config, $container->get(Config::class));
        self::assertSame($accountType, $container->get(AccountType::class));
    }

    private function assertDefinitionsDoNotContain(
        Config $config,
        AccountType $accountType,
        string ...$secrets,
    ): void {
        $definitions = serialize(new ContainerFactory($this->createTempDirectory(), false)->buildDefinitions()->getDefinitions());

        self::assertStringNotContainsString(serialize($config), $definitions);
        self::assertStringNotContainsString(serialize($accountType), $definitions);
        foreach ($secrets as $secret) {
            self::assertStringNotContainsString($secret, $definitions);
        }
    }

    private function individualConfig(string $secret): Config
    {
        return $this->createConfig([
            'JIRA_URL' => 'https://jira.example',
            'ATLASSIAN_EMAIL' => 'user@example.com',
            'ATLASSIAN_API_TOKEN' => $secret,
            'REPORT_EXPORT_ENABLED' => 'false',
            'DAILY_HOURS_LIMIT' => '8',
            'APP_TIMEZONE' => 'Europe/Warsaw',
            'HOLIDAY_COUNTRY' => 'Poland',
        ]);
    }
}
