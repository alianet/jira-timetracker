<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App;

use App\Composition\ContainerFactory;
use App\Identity\Application\Authentication\AccountType;
use App\Kernel\Config\Config;
use App\Kernel\Config\LocaleConfig;
use App\Kernel\Config\TemplateConfig;
use App\Kernel\Infrastructure\Logging\LoggerFactory;
use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use App\Kernel\Presentation\Http\FrontController;
use App\Kernel\Presentation\Http\LocalhostRequestChecker;
use App\Kernel\Presentation\Http\SessionInitializer;
use App\Kernel\Presentation\Locale\LocaleResolver;
use App\Kernel\Presentation\Twig\EnvironmentFactory;
use App\Kernel\Session\Session;
use Symfony\Component\Translation\Translator;
use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

final readonly class Bootstrap
{
    private const string ENV_LOG_LEVEL = 'LOG_LEVEL';
    private const string ENV_JIRA_URL = 'JIRA_URL';
    private const string ENV_ATLASSIAN_ACCOUNT_TYPE = 'ATLASSIAN_ACCOUNT_TYPE';

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

        $session = new SessionInitializer()->start($server);
        $sessionLocale = $session->get('locale');
        $localeResolver = new LocaleResolver($this->localeConfig);
        $locale = $localeResolver->resolve(
            $server,
            is_string($sessionLocale) ? $sessionLocale : null,
        );
        $this->persistLocale($session, $locale, $server);

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
        $twig->addGlobal('jiraFaviconUrl', $this->jiraFaviconUrl($jiraUrl));

        $container = new ContainerFactory(
            $this->rootDirectory . '/var/cache/container',
            $logLevel === 'debug',
        )->create(
            $this->config,
            $session,
            $this->translator,
            $twig,
            $logger,
            $accountType,
        );
        $frontController = $container->get(FrontController::class);
        if (!$frontController instanceof FrontController) {
            throw new \LogicException('Application container did not provide the front controller.');
        }
        $frontController->handle($server);
    }

    /**
     * @param array<string, mixed> $server
     */
    private function persistLocale(Session $session, string $locale, array $server): void
    {
        $session->set('locale', $locale);
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
}
