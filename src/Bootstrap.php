<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App;

use App\Identity\Application\Authentication\AccessTokenStore;
use App\Identity\Application\Authentication\AccountType;
use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Application\Authentication\AuthorizationGateway;
use App\Identity\Application\Authentication\CompleteAuthorizationHandler;
use App\Identity\Application\Authentication\Connection;
use App\Identity\Application\Authentication\ConnectionProvider;
use App\Identity\Application\Authentication\LogoutHandler;
use App\Identity\Application\Authentication\StartAuthorizationHandler;
use App\Identity\Application\Query\SearchUsersHandler;
use App\Identity\Application\Query\UserDirectory;
use App\Identity\Infrastructure\Atlassian\AtlassianHttpClient;
use App\Identity\Infrastructure\Atlassian\AtlassianOAuth;
use App\Identity\Infrastructure\Atlassian\AtlassianPersonalAccess;
use App\Identity\Infrastructure\Atlassian\AtlassianUserDirectory;
use App\Identity\Infrastructure\Session\NativeSessionSecurity;
use App\Identity\Infrastructure\Session\SessionAccessTokenStore;
use App\Identity\Infrastructure\Session\SessionTokenCipher;
use App\Identity\Presentation\Http\AuthenticationController;
use App\Identity\Presentation\Http\Authorization;
use App\Identity\Presentation\Http\SearchUsersController;
use App\Identity\Presentation\Http\UserSearchEndpoint;
use App\Kernel\Config\Config;
use App\Kernel\Config\LocaleConfig;
use App\Kernel\Config\ReportExportConfig;
use App\Kernel\Config\TemplateConfig;
use App\Kernel\Config\WorkdayConfig;
use App\Kernel\Infrastructure\Logging\LoggerFactory;
use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use App\Kernel\Presentation\Http\Dispatcher;
use App\Kernel\Presentation\Http\FrontController;
use App\Kernel\Presentation\Http\LocalhostRequestChecker;
use App\Kernel\Presentation\Http\Route;
use App\Kernel\Presentation\Http\Router;
use App\Kernel\Presentation\Http\SessionInitializer;
use App\Kernel\Presentation\Locale\LocaleResolver;
use App\Kernel\Presentation\Twig\EnvironmentFactory;
use App\Reporting\Application\ExportMonthlyReportHandler;
use App\Reporting\Application\GenerateMonthlyReportHandler;
use App\Reporting\Application\Port\WorklogReportSource;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorkdayPolicy;
use App\Reporting\Infrastructure\Calendar\HolidayCalendarFactory;
use App\Reporting\Infrastructure\Csv\CsvReportExporter;
use App\Reporting\Infrastructure\Jira\JiraReportClient;
use App\Reporting\Infrastructure\Jira\JiraWorklogReportSource;
use App\Reporting\Presentation\Http\ReportCsvController;
use App\Reporting\Presentation\Http\ReportPageController;
use App\Reporting\Presentation\ViewModel\ReportViewFactory;
use App\Reporting\Presentation\ViewModel\WorklogTagProvider;
use App\Shared\Infrastructure\Http\SymfonyJsonHttpTransport;
use App\Shared\Infrastructure\SystemClock;
use App\TimeTracking\Application\Handler\AddWorklogHandler;
use App\TimeTracking\Application\Handler\DeleteWorklogHandler;
use App\TimeTracking\Application\Handler\UpdateWorklogHandler;
use App\TimeTracking\Application\Port\WorklogGateway;
use App\TimeTracking\Application\Query\IssueDirectory;
use App\TimeTracking\Application\Query\SearchIssuesHandler;
use App\TimeTracking\Infrastructure\Jira\JiraHttpClient;
use App\TimeTracking\Infrastructure\Jira\JiraIssueDirectory;
use App\TimeTracking\Infrastructure\Jira\JiraTimeFormat;
use App\TimeTracking\Infrastructure\Jira\JiraTransport;
use App\TimeTracking\Infrastructure\Jira\JiraWorklogRepository;
use App\TimeTracking\Presentation\Http\IssueSearchEndpoint;
use App\TimeTracking\Presentation\Http\SearchIssuesController;
use App\TimeTracking\Presentation\Http\WorklogController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

final readonly class Bootstrap
{
    private const string ENV_LOG_LEVEL = 'LOG_LEVEL';
    private const string ENV_JIRA_URL = 'JIRA_URL';
    private const string ENV_SESSION_ENCRYPTION_KEY = 'SESSION_ENCRYPTION_KEY';
    private const string ENV_HOLIDAY_COUNTRY = 'HOLIDAY_COUNTRY';
    private const string ENV_HOLIDAY_CALENDAR_VERSION = 'HOLIDAY_CALENDAR_VERSION';
    private const string ENV_ATLASSIAN_ACCOUNT_TYPE = 'ATLASSIAN_ACCOUNT_TYPE';
    private const string ENV_ATLASSIAN_EMAIL = 'ATLASSIAN_EMAIL';
    private const string ENV_ATLASSIAN_API_TOKEN = 'ATLASSIAN_API_TOKEN';
    private const string ENV_ATLASSIAN_CLIENT_ID = 'ATLASSIAN_CLIENT_ID';
    private const string ENV_ATLASSIAN_CLIENT_SECRET = 'ATLASSIAN_CLIENT_SECRET';
    private const string ENV_ATLASSIAN_REDIRECT_URI = 'ATLASSIAN_REDIRECT_URI';

    private string $rootDirectory;
    private Config $config;
    private LocaleConfig $localeConfig;
    private Translator $translator;
    private EnvironmentFactory $twigFactory;

    public function __construct(string $rootDirectory)
    {
        $this->rootDirectory = $rootDirectory;
        $this->config = Config::fromDirectory($rootDirectory);
        $this->localeConfig = LocaleConfig::fromConfig($this->config);
        $this->translator = new TranslatorFactory()->create(
            $rootDirectory . '/translations',
            $this->localeConfig->locales,
            $this->localeConfig->defaultLocale,
        );
        $this->twigFactory = new EnvironmentFactory();
    }

    /**
     * @param array<string, mixed> $server
     * @throws InvalidUriException
     */
    public function run(array $server): void
    {
        $logLevel = $this->config->choice(
            self::ENV_LOG_LEVEL,
            ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'],
            'error',
        );
        $logger = new LoggerFactory()->create($this->rootDirectory . '/var/log/app.log', $logLevel);
        $accountType = $this->accountType();
        if ($accountType === AccountType::Individual && !new LocalhostRequestChecker()->isLocalhost($server)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store');
            echo 'Individual mode is available only on localhost.';

            return;
        }

        new SessionInitializer()->start($server);
        $sessionLocale = $_SESSION['locale'] ?? null;
        $localeResolver = new LocaleResolver($this->localeConfig);
        $locale = $localeResolver->resolve(
            $server,
            is_string($sessionLocale) ? $sessionLocale : null,
        );
        $this->persistLocale($locale, $server);

        $redirectUri = $localeResolver->redirectUriWithoutRequestedLocale($server);
        if ($redirectUri !== null) {
            header('Location: ' . $redirectUri, true, 303);

            return;
        }

        $this->translator->setLocale($locale);
        $twig = $this->twigFactory->create($this->rootDirectory . '/templates', $this->translator);
        $twig->addGlobal('availableLocales', $this->localeConfig->locales);
        $twig->addGlobal('currentLocale', $locale);
        $twig->addGlobal('templateVariant', TemplateConfig::fromConfig($this->config)->variant);
        $jiraUrl = $this->config->required(self::ENV_JIRA_URL);
        $baseHttpClient = HttpClient::create();
        $sessionTokenCipher = $accountType === AccountType::Company
            ? new SessionTokenCipher($this->config->required(self::ENV_SESSION_ENCRYPTION_KEY))
            : null;
        $twig->addGlobal('jiraFaviconUrl', $this->jiraFaviconUrl($jiraUrl));
        $tokenStore = fn(array &$session): SessionAccessTokenStore => new SessionAccessTokenStore(
            $session,
            $sessionTokenCipher,
        );
        $gateway = fn(array &$session): AuthorizationGateway&ConnectionProvider => $this->atlassianAccess(
            $accountType,
            $jiraUrl,
            $tokenStore($session),
            $baseHttpClient,
            $logger,
        );
        $exportConfig = ReportExportConfig::fromConfig($this->config);
        $workdayConfig = WorkdayConfig::fromConfig($this->config);
        $holidayCalendar = new HolidayCalendarFactory()->create(
            $this->config->required(self::ENV_HOLIDAY_COUNTRY),
            $this->config->nullable(self::ENV_HOLIDAY_CALENDAR_VERSION) ?? HolidayCalendarFactory::VERSION_NATIONAL,
        );
        $dailySeconds = $workdayConfig->reportingDaySeconds;
        $reportTimezone = ReportTimeZone::fromName($workdayConfig->timezone);
        $timezone = new \DateTimeZone($workdayConfig->timezone);

        // Request-scoped provider wiring. Identity only hands over a Connection; turning it into
        // Reporting and TimeTracking adapters happens here, in the composition root. Both the connection and
        // the HTTP client are resolved once per request, so a token refresh and a TCP connection pool are
        // shared by every adapter built from the same credentials.
        $connection = null;
        $httpClient = null;
        $connect = function (array &$session) use (&$connection, $gateway): Connection {
            return $connection ??= $gateway($session)->connection();
        };
        $authorizedHttp = function (array &$session) use (&$httpClient, $baseHttpClient, $connect): HttpClientInterface {
            return $httpClient ??= $baseHttpClient->withOptions(self::authOptions($connect($session)));
        };
        $jiraTransport = fn(array &$session): JiraTransport => new JiraHttpClient(
            new SymfonyJsonHttpTransport($connect($session)->apiBaseUrl, $authorizedHttp($session), $logger),
            $this->translator,
        );
        $reportSource = fn(array &$session): WorklogReportSource => new JiraWorklogReportSource(
            new JiraReportClient(
                $connect($session)->siteUrl,
                new SymfonyJsonHttpTransport($connect($session)->apiBaseUrl, $authorizedHttp($session), $logger),
                $this->translator,
            ),
            $reportTimezone,
        );
        $worklogGateway = fn(array &$session): WorklogGateway => new JiraWorklogRepository($jiraTransport($session), $timezone);
        $jiraTimeUnits = JiraTimeFormat::units();
        $issueDirectory = fn(array &$session): IssueDirectory => new JiraIssueDirectory(
            $jiraTransport($session),
            $connect($session)->siteUrl,
        );
        $userDirectory = fn(array &$session): UserDirectory => new AtlassianUserDirectory(new AtlassianHttpClient(
            new SymfonyJsonHttpTransport($connect($session)->apiBaseUrl, $authorizedHttp($session), $logger),
            $this->translator,
        ));

        $generateReport = fn(array &$session): GenerateMonthlyReportHandler => new GenerateMonthlyReportHandler(
            $reportSource($session),
            new WorkdayPolicy(
                $dailySeconds,
                $holidayCalendar,
                new SystemClock($timezone),
                $reportTimezone,
            ),
            $dailySeconds,
        );
        $reportViewFactory = fn(array &$session): ReportViewFactory => new ReportViewFactory(
            $this->translator,
            self::issueUrl($connect($session)->siteUrl),
        );
        $exportReport = fn(array &$session): ExportMonthlyReportHandler => new ExportMonthlyReportHandler(
            $reportSource($session),
            new CsvReportExporter($exportConfig, $this->translator, self::issueUrl($connect($session)->siteUrl)),
        );
        $issueSearch = fn(array &$session): SearchIssuesController => new SearchIssuesController(
            new SearchIssuesHandler($issueDirectory($session)),
            $logger,
        );
        $userSearch = fn(array &$session): SearchUsersController => new SearchUsersController(
            new SearchUsersHandler($userDirectory($session)),
            $logger,
        );
        $addWorklog = fn(array &$session): AddWorklogHandler => new AddWorklogHandler($worklogGateway($session), $jiraTimeUnits);
        $updateWorklog = fn(array &$session): UpdateWorklogHandler => new UpdateWorklogHandler($worklogGateway($session), $jiraTimeUnits);
        $deleteWorklog = fn(array &$session): DeleteWorklogHandler => new DeleteWorklogHandler($worklogGateway($session));

        $auth = new AuthenticationController(
            fn(array &$session): StartAuthorizationHandler => new StartAuthorizationHandler($gateway($session)),
            fn(array &$session): CompleteAuthorizationHandler => new CompleteAuthorizationHandler(
                $gateway($session),
                $tokenStore($session),
            ),
            fn(array &$session): LogoutHandler => new LogoutHandler($tokenStore($session)),
            new NativeSessionSecurity(),
            $logger,
        );
        $worklogs = new WorklogController($addWorklog, $updateWorklog, $deleteWorklog, $logger);
        $report = new ReportPageController(
            $twig,
            $generateReport,
            $reportViewFactory,
            new WorklogTagProvider(),
            $exportConfig->enabled,
            $reportTimezone,
            $logger,
        );
        $csv = new ReportCsvController(
            $exportReport,
            $exportConfig->enabled,
            $this->translator->trans('csv.export_disabled'),
            $reportTimezone,
            $logger,
        );
        $issues = new IssueSearchEndpoint($issueSearch);
        $users = new UserSearchEndpoint($userSearch);
        $routes = [
            new Route('login', '/login', ['GET'], $auth->login(...), true),
            new Route('oauth_callback', '/oauth/callback', ['GET'], $auth->callback(...), true),
            new Route('logout', '/logout', ['POST'], $auth->logout(...), true),
            new Route('issues_search', '/api/issues/search', ['GET'], $issues->search(...)),
            new Route('users_search', '/api/users/search', ['GET'], $users->search(...)),
            new Route('report_export', '/export.csv', ['GET'], $csv->export(...)),
            new Route('worklog_create', '/worklogs', ['POST'], $worklogs->create(...)),
            new Route('worklog_update', '/worklogs/{id}', ['POST'], $worklogs->update(...), requirements: ['id' => '\\d+']),
            new Route('worklog_delete', '/worklogs/{id}/delete', ['POST'], $worklogs->delete(...), requirements: ['id' => '\\d+']),
            // Migration compatibility: remove after external clients stop posting action/worklog_id to `/`.
            new Route('worklog_legacy', '/', ['POST'], $worklogs->legacy(...)),
            new Route('report', '/', ['GET'], $report->show(...)),
        ];
        $authorization = new Authorization(
            $connect,
            $twig,
            $accountType,
        );
        $dispatcher = new Dispatcher(new Router($routes), $authorization->requireAuthenticated(...));
        new FrontController($dispatcher, $twig, $this->translator, $logger)->handle($server);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function persistLocale(string $locale, array $server): void
    {
        $_SESSION['locale'] = $locale;
        \setcookie($this->localeConfig->cookieName, $locale, [
            'expires' => time() + $this->localeConfig->cookieTtl,
            'path' => '/',
            'secure' => !empty($server['HTTPS']) && $server['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function accountType(): AccountType
    {
        $accountType = $this->config->choice(
            self::ENV_ATLASSIAN_ACCOUNT_TYPE,
            AccountType::values(),
            AccountType::Individual->value,
        );

        return AccountType::from($accountType);
    }

    /**
     * @throws InvalidUriException
     */
    private function jiraFaviconUrl(string $jiraUrl): string
    {
        $uri = new Uri($jiraUrl);

        if ($uri->getScheme() === null || $uri->getHost() === null) {
            throw new \RuntimeException(self::ENV_JIRA_URL . ' must be an absolute URL.');
        }

        return $uri
            ->withUserInfo(null)
            ->withPath('/favicon.ico')
            ->withQuery(null)
            ->withFragment(null)
            ->toString();
    }

    private function atlassianAccess(
        AccountType $accountType,
        string $jiraUrl,
        AccessTokenStore $tokenStore,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
    ): AuthorizationGateway&ConnectionProvider {
        if ($accountType === AccountType::Individual) {
            return new AtlassianPersonalAccess(
                $jiraUrl,
                $this->config->required(self::ENV_ATLASSIAN_EMAIL),
                $this->config->required(self::ENV_ATLASSIAN_API_TOKEN),
            );
        }

        return new AtlassianOAuth(
            $this->config->required(self::ENV_ATLASSIAN_CLIENT_ID),
            $this->config->required(self::ENV_ATLASSIAN_CLIENT_SECRET),
            $this->config->required(self::ENV_ATLASSIAN_REDIRECT_URI),
            $jiraUrl,
            $httpClient,
            $tokenStore,
            $logger,
        );
    }

    /**
     * Maps Identity credentials onto HTTP client options — the only place that has to know both sides.
     *
     * @return array<string, mixed>
     */
    private static function authOptions(Connection $connection): array
    {
        return match ($connection->mode) {
            AuthenticationMode::InteractiveOAuth => ['auth_bearer' => $connection->token],
            AuthenticationMode::PersonalToken => ['auth_basic' => [$connection->login, $connection->token]],
        };
    }

    /** @return \Closure(string): string */
    private static function issueUrl(string $siteUrl): \Closure
    {
        return static fn(string $issue): string => rtrim($siteUrl, '/') . '/browse/' . $issue;
    }
}
