<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Composition;

use App\Identity\Application\Authentication\AccessTokenStore;
use App\Identity\Application\Authentication\AccountType;
use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Application\Authentication\AuthorizationGateway;
use App\Identity\Application\Authentication\CompleteAuthorizationHandler;
use App\Identity\Application\Authentication\Connection;
use App\Identity\Application\Authentication\ConnectionProvider;
use App\Identity\Application\Authentication\LogoutHandler;
use App\Identity\Application\Authentication\StartAuthorizationHandler;
use App\Identity\Infrastructure\Atlassian\AtlassianOAuth;
use App\Identity\Infrastructure\Atlassian\AtlassianPersonalAccess;
use App\Identity\Infrastructure\Session\SessionAccessTokenStore;
use App\Identity\Infrastructure\Session\SessionTokenCipher;
use App\Identity\Presentation\Http\AuthenticationController;
use App\Identity\Presentation\Http\Authorization;
use App\Identity\Presentation\Http\SearchUsersController;
use App\Identity\Presentation\Http\UserSearchEndpoint;
use App\Kernel\Config\Config;
use App\Kernel\Config\ReportExportConfig;
use App\Kernel\Config\WorkdayConfig;
use App\Kernel\Presentation\Http\FrontController;
use App\Kernel\Presentation\Http\Route;
use App\Kernel\Presentation\Http\Router;
use App\Kernel\Session\Session;
use App\Reporting\Application\ExportMonthlyReportHandler;
use App\Reporting\Application\GenerateMonthlyReportHandler;
use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Domain\Port\HolidayCalendar;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorkdayPolicy;
use App\Reporting\Infrastructure\Calendar\HolidayCalendarFactory;
use App\Reporting\Infrastructure\Csv\CsvReportExporter;
use App\Reporting\Infrastructure\Jira\JiraReportClient;
use App\Reporting\Presentation\Http\ReportCsvController;
use App\Reporting\Presentation\Http\ReportPageController;
use App\Reporting\Presentation\ViewModel\ReportViewFactory;
use App\Reporting\Presentation\ViewModel\WorklogTagProvider;
use App\Shared\Domain\Clock;
use App\Shared\Infrastructure\Http\SymfonyJsonHttpTransport;
use App\Shared\Infrastructure\SystemClock;
use App\TimeTracking\Application\Handler\AddWorklogHandler;
use App\TimeTracking\Application\Handler\DeleteWorklogHandler;
use App\TimeTracking\Application\Handler\UpdateWorklogHandler;
use App\TimeTracking\Domain\Model\WorkTimeUnits;
use App\TimeTracking\Infrastructure\Jira\JiraIssueDirectory;
use App\TimeTracking\Infrastructure\Jira\JiraTimeFormat;
use App\TimeTracking\Infrastructure\Jira\JiraTransport;
use App\TimeTracking\Presentation\Http\IssueSearchEndpoint;
use App\TimeTracking\Presentation\Http\SearchIssuesController;
use App\TimeTracking\Presentation\Http\WorklogController;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class ContainerFactory
{
    private const string ATLASSIAN_ACCESS = 'app.atlassian.access';
    private const string AUTHORIZED_HTTP_CLIENT = 'app.http_client.authorized';
    private const string BASE_HTTP_CLIENT = 'app.http_client.base';

    private readonly CompiledContainerCache $cache;
    private readonly string $projectDirectory;

    public function __construct(string $cacheDirectory, bool $debug)
    {
        $this->projectDirectory = dirname(__DIR__, 2);
        $this->cache = new CompiledContainerCache(
            rtrim($cacheDirectory, '/') . '/AppContainer.php',
            $debug,
        );
    }

    public function create(
        Config $config,
        Session $session,
        Translator $translator,
        Environment $twig,
        LoggerInterface $logger,
        AccountType $accountType,
    ): Container {
        $container = $this->cache->load($this->buildDefinitions(...));
        $services = $this->runtimeServices($config, $session, $translator, $twig, $logger, $accountType);
        $this->hydrate($container, $services);

        return $container;
    }

    public function warmUp(): void
    {
        $this->cache->warmUp($this->buildDefinitions(...));
    }

    public function buildDefinitions(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addResource(new FileResource($this->projectDirectory . '/config/services.php'));
        $container->addResource(new DirectoryResource($this->projectDirectory . '/src', '/\.php$/'));
        $container->addResource(new FileResource(__FILE__));
        new PhpFileLoader($container, new FileLocator($this->projectDirectory . '/config'))->load('services.php');
        $this->registerRuntimeServiceDefinitions($container);
        $this->registerIdentity($container);
        $this->registerJira($container);
        $this->registerReporting($container);
        $this->registerTimeTracking($container);
        $this->registerPresentation($container);
        $container->compile();

        return $container;
    }

    /** @return array<string, object> */
    public function runtimeServices(
        Config $config,
        Session $session,
        Translator $translator,
        Environment $twig,
        LoggerInterface $logger,
        AccountType $accountType,
    ): array {
        $workdayConfig = WorkdayConfig::fromConfig($config);

        return [
            Config::class => $config,
            Session::class => $session,
            Translator::class => $translator,
            Environment::class => $twig,
            LoggerInterface::class => $logger,
            AccountType::class => $accountType,
            self::BASE_HTTP_CLIENT => HttpClient::create(),
            ReportExportConfig::class => ReportExportConfig::fromConfig($config),
            WorkdayConfig::class => $workdayConfig,
            ReportTimeZone::class => ReportTimeZone::fromName($workdayConfig->timezone),
            \DateTimeZone::class => new \DateTimeZone($workdayConfig->timezone),
            HolidayCalendar::class => new HolidayCalendarFactory()->create(
                $config->required('HOLIDAY_COUNTRY'),
                $config->nullable('HOLIDAY_CALENDAR_VERSION') ?? HolidayCalendarFactory::VERSION_NATIONAL,
            ),
            WorkTimeUnits::class => JiraTimeFormat::units(),
        ];
    }

    /** @param array<string, object> $services */
    public function hydrate(Container $container, array $services): void
    {
        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }
    }

    private function registerRuntimeServiceDefinitions(ContainerBuilder $container): void
    {
        $this->registerSynthetic($container, Config::class, Config::class);
        $this->registerSynthetic($container, Session::class, Session::class);
        $this->registerSynthetic($container, Translator::class, Translator::class);
        $container->setAlias(TranslatorInterface::class, Translator::class);
        $this->registerSynthetic($container, Environment::class, Environment::class);
        $this->registerSynthetic($container, LoggerInterface::class, LoggerInterface::class);
        $this->registerSynthetic($container, AccountType::class, AccountType::class);
        $this->registerSynthetic($container, self::BASE_HTTP_CLIENT, HttpClientInterface::class);
        $this->registerSynthetic($container, ReportExportConfig::class, ReportExportConfig::class);
        $this->registerSynthetic($container, WorkdayConfig::class, WorkdayConfig::class);
        $this->registerSynthetic($container, ReportTimeZone::class, ReportTimeZone::class);
        $this->registerSynthetic($container, \DateTimeZone::class, \DateTimeZone::class);
        $this->registerSynthetic($container, HolidayCalendar::class, HolidayCalendar::class);
        $this->registerSynthetic($container, WorkTimeUnits::class, WorkTimeUnits::class);
    }

    private function registerIdentity(ContainerBuilder $container): void
    {
        $container->autowire(SessionAccessTokenStore::class)
            ->setFactory([self::class, 'sessionAccessTokenStore']);
        $container->autowire(self::ATLASSIAN_ACCESS, ConnectionProvider::class)
            ->setFactory([self::class, 'atlassianAccess'])
            ->setArgument('$httpClient', new Reference(self::BASE_HTTP_CLIENT));
        $container->setAlias(AuthorizationGateway::class, self::ATLASSIAN_ACCESS);
    }

    private function registerJira(ContainerBuilder $container): void
    {
        $container->autowire(Connection::class)
            ->setFactory([new Reference(self::ATLASSIAN_ACCESS), 'connection'])
            ->setPublic(true);
        $container->autowire(self::AUTHORIZED_HTTP_CLIENT, HttpClientInterface::class)
            ->setFactory([self::class, 'authorizedHttpClient'])
            ->setArgument('$baseHttpClient', new Reference(self::BASE_HTTP_CLIENT));
        $container->autowire(SymfonyJsonHttpTransport::class)
            ->setFactory([self::class, 'jiraJsonTransport'])
            ->setArgument('$httpClient', new Reference(self::AUTHORIZED_HTTP_CLIENT));
    }

    private function registerReporting(ContainerBuilder $container): void
    {
        $container->autowire(JiraReportClient::class)
            ->setFactory([self::class, 'jiraReportClient']);
        $container->setAlias(Clock::class, SystemClock::class);
        $container->autowire(WorkdayPolicy::class)
            ->setFactory([self::class, 'workdayPolicy']);
        $container->autowire(GenerateMonthlyReportHandler::class)
            ->setFactory([self::class, 'generateMonthlyReportHandler']);
        $container->autowire(CsvReportExporter::class)
            ->setFactory([self::class, 'csvReportExporter']);
        $container->autowire(ReportViewFactory::class)
            ->setFactory([self::class, 'reportViewFactory']);
    }

    private function registerTimeTracking(ContainerBuilder $container): void
    {
        $container->autowire(JiraIssueDirectory::class)
            ->setFactory([self::class, 'jiraIssueDirectory']);
    }

    private function registerPresentation(ContainerBuilder $container): void
    {
        $handler = static fn(string $id): ServiceClosureArgument => new ServiceClosureArgument(new Reference($id));

        $container->autowire(AuthenticationController::class)
            ->setArgument('$start', $handler(StartAuthorizationHandler::class))
            ->setArgument('$complete', $handler(CompleteAuthorizationHandler::class))
            ->setArgument('$logout', $handler(LogoutHandler::class));
        $container->autowire(WorklogController::class)
            ->setArgument('$addHandler', $handler(AddWorklogHandler::class))
            ->setArgument('$updateHandler', $handler(UpdateWorklogHandler::class))
            ->setArgument('$deleteHandler', $handler(DeleteWorklogHandler::class));
        $container->autowire(ReportPageController::class)
            ->setFactory([self::class, 'reportPageController'])
            ->setArgument('$generateReport', $handler(GenerateMonthlyReportHandler::class))
            ->setArgument('$viewFactory', $handler(ReportViewFactory::class));
        $container->autowire(ReportCsvController::class)
            ->setFactory([self::class, 'reportCsvController'])
            ->setArgument('$exportReport', $handler(ExportMonthlyReportHandler::class));
        $container->autowire(IssueSearchEndpoint::class)
            ->setArgument('$controller', $handler(SearchIssuesController::class));
        $container->autowire(UserSearchEndpoint::class)
            ->setArgument('$controller', $handler(SearchUsersController::class));
        $container->autowire(Authorization::class)
            ->setArgument('$connection', $handler(Connection::class));
        $container->autowire(Router::class)
            ->setFactory([self::class, 'router']);
        $container->autowire(FrontController::class)->setPublic(true);
    }

    public static function atlassianAccess(
        AccountType $accountType,
        Config $config,
        AccessTokenStore $tokenStore,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
    ): AuthorizationGateway&ConnectionProvider {
        $jiraUrl = $config->required('JIRA_URL');
        if ($accountType === AccountType::Individual) {
            return new AtlassianPersonalAccess(
                $jiraUrl,
                $config->required('ATLASSIAN_EMAIL'),
                $config->required('ATLASSIAN_API_TOKEN'),
            );
        }

        return new AtlassianOAuth(
            $config->required('ATLASSIAN_CLIENT_ID'),
            $config->required('ATLASSIAN_CLIENT_SECRET'),
            $config->required('ATLASSIAN_REDIRECT_URI'),
            $jiraUrl,
            $httpClient,
            $tokenStore,
            $logger,
        );
    }

    public static function sessionAccessTokenStore(
        Session $session,
        Config $config,
        AccountType $accountType,
    ): SessionAccessTokenStore {
        $cipher = $accountType === AccountType::Company
            ? new SessionTokenCipher($config->required('SESSION_ENCRYPTION_KEY'))
            : null;

        return new SessionAccessTokenStore($session, $cipher);
    }

    public static function authorizedHttpClient(
        HttpClientInterface $baseHttpClient,
        Connection $connection,
    ): HttpClientInterface {
        $options = match ($connection->mode) {
            AuthenticationMode::InteractiveOAuth => ['auth_bearer' => $connection->token],
            AuthenticationMode::PersonalToken => ['auth_basic' => [$connection->login, $connection->token]],
        };

        return $baseHttpClient->withOptions($options);
    }

    public static function jiraJsonTransport(
        Connection $connection,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
    ): SymfonyJsonHttpTransport {
        return new SymfonyJsonHttpTransport($connection->apiBaseUrl, $httpClient, $logger);
    }

    public static function jiraReportClient(
        Connection $connection,
        SymfonyJsonHttpTransport $transport,
        TranslatorInterface $translator,
    ): JiraReportClient {
        return new JiraReportClient($connection->siteUrl, $transport, $translator);
    }

    public static function workdayPolicy(
        WorkdayConfig $config,
        HolidayCalendar $holidayCalendar,
        Clock $clock,
        ReportTimeZone $timezone,
    ): WorkdayPolicy {
        return new WorkdayPolicy($config->reportingDaySeconds, $holidayCalendar, $clock, $timezone);
    }

    public static function generateMonthlyReportHandler(
        WorklogReportSource $source,
        WorkdayPolicy $workdayPolicy,
        WorkdayConfig $config,
    ): GenerateMonthlyReportHandler {
        return new GenerateMonthlyReportHandler($source, $workdayPolicy, $config->reportingDaySeconds);
    }

    public static function jiraIssueDirectory(
        JiraTransport $transport,
        Connection $connection,
    ): JiraIssueDirectory {
        return new JiraIssueDirectory($transport, $connection->siteUrl);
    }

    public static function reportViewFactory(
        TranslatorInterface $translator,
        Connection $connection,
    ): ReportViewFactory {
        return new ReportViewFactory($translator, self::issueUrl($connection->siteUrl));
    }

    public static function csvReportExporter(
        ReportExportConfig $config,
        TranslatorInterface $translator,
        Connection $connection,
    ): CsvReportExporter {
        return new CsvReportExporter($config, $translator, self::issueUrl($connection->siteUrl));
    }

    /**
     * @param callable(): GenerateMonthlyReportHandler $generateReport
     * @param callable(): ReportViewFactory $viewFactory
     */
    public static function reportPageController(
        Environment $twig,
        callable $generateReport,
        callable $viewFactory,
        WorklogTagProvider $tagProvider,
        ReportExportConfig $config,
        ReportTimeZone $timezone,
        LoggerInterface $logger,
    ): ReportPageController {
        return new ReportPageController(
            $twig,
            $generateReport,
            $viewFactory,
            $tagProvider,
            $config->enabled,
            $timezone,
            $logger,
        );
    }

    /** @param callable(): ExportMonthlyReportHandler $exportReport */
    public static function reportCsvController(
        callable $exportReport,
        ReportExportConfig $config,
        TranslatorInterface $translator,
        ReportTimeZone $timezone,
        LoggerInterface $logger,
    ): ReportCsvController {
        return new ReportCsvController($exportReport, $config->enabled, $translator, $timezone, $logger);
    }

    public static function router(
        AuthenticationController $authentication,
        WorklogController $worklogs,
        ReportPageController $report,
        ReportCsvController $csv,
        IssueSearchEndpoint $issues,
        UserSearchEndpoint $users,
    ): Router {
        return new Router([
            new Route('login', '/login', ['GET'], $authentication->login(...), true),
            new Route('oauth_callback', '/oauth/callback', ['GET'], $authentication->callback(...), true),
            new Route('logout', '/logout', ['POST'], $authentication->logout(...), true),
            new Route('issues_search', '/api/issues/search', ['GET'], $issues->search(...)),
            new Route('users_search', '/api/users/search', ['GET'], $users->search(...)),
            new Route('report_export', '/export.csv', ['GET'], $csv->export(...)),
            new Route('worklog_create', '/worklogs', ['POST'], $worklogs->create(...)),
            new Route('worklog_update', '/worklogs/{id}', ['POST'], $worklogs->update(...), requirements: ['id' => '\\d+']),
            new Route('worklog_delete', '/worklogs/{id}/delete', ['POST'], $worklogs->delete(...), requirements: ['id' => '\\d+']),
            new Route('worklog_legacy', '/', ['POST'], $worklogs->legacy(...)),
            new Route('report', '/', ['GET'], $report->show(...)),
        ]);
    }

    /** @return \Closure(string): string */
    private static function issueUrl(string $siteUrl): \Closure
    {
        return static fn(string $issue): string => rtrim($siteUrl, '/') . '/browse/' . $issue;
    }

    /** @param class-string $class */
    private function registerSynthetic(ContainerBuilder $container, string $id, string $class): void
    {
        $container->register($id, $class)
            ->setSynthetic(true)
            ->setPublic(true);
    }
}
