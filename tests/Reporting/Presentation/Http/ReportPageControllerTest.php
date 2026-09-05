<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Reporting\Presentation\Http;

use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Twig\EnvironmentFactory;
use App\Reporting\Application\GenerateMonthlyReportHandler;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorkdayPolicy;
use App\Reporting\Infrastructure\Calendar\HolidayCalendarFactory;
use App\Reporting\Infrastructure\Jira\JiraReportClient;
use App\Reporting\Infrastructure\Jira\JiraWorklogReportSource;
use App\Reporting\Presentation\Http\ReportPageController;
use App\Reporting\Presentation\ViewModel\ReportView;
use App\Reporting\Presentation\ViewModel\ReportViewFactory;
use App\Reporting\Presentation\ViewModel\WorklogTagProvider;
use App\Shared\Infrastructure\FixedClock;
use App\Shared\Infrastructure\Http\SymfonyJsonHttpTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\TestCase;
use Twig\Environment;

final class ReportPageControllerTest extends TestCase
{
    public function testBuildsTheExistingTemplateContractForHistoricalMonth(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"accountId":"me","displayName":"User"}'),
            new MockResponse('{"issues":[]}'),
        ]);
        $translator = new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('report/index.html.twig', self::callback(static function (array $context): bool {
                self::assertSame(['report'], array_keys($context));
                self::assertInstanceOf(ReportView::class, $context['report']);
                self::assertSame(2024, $context['report']->year);
                self::assertSame(2, $context['report']->month);
                self::assertSame('2024-02-01', $context['report']->startDate);
                self::assertSame('2024-02-29', $context['report']->endDate);
                self::assertSame('2024-02-01', $context['report']->defaultWorklogDate);
                self::assertSame('csrf', $context['report']->csrfToken);
                self::assertSame('Site', $context['report']->siteName);
                self::assertTrue($context['report']->exportEnabled);
                self::assertTrue($context['report']->saved);
                self::assertCount(29, $context['report']->days);
                self::assertNotEmpty($context['report']->worklogTags);

                return true;
            }))
            ->willReturn('rendered report');
        $jira = new JiraReportClient('https://site.test', new SymfonyJsonHttpTransport('https://api.test', $http), $translator);
        $timezone = ReportTimeZone::fromName('Europe/Warsaw');
        $generate = new GenerateMonthlyReportHandler(
            new JiraWorklogReportSource($jira, $timezone),
            new WorkdayPolicy(28800, new HolidayCalendarFactory()->create(HolidayCalendarFactory::COUNTRY_POLAND), new FixedClock(new \DateTimeImmutable('2024-03-01')), $timezone),
            28800,
        );
        $controller = new ReportPageController(
            $twig,
            static fn(array &$session): GenerateMonthlyReportHandler => $generate,
            static fn(array &$session): ReportViewFactory => new ReportViewFactory($translator, $jira->issueUrl(...)),
            new WorklogTagProvider(),
            true,
            $timezone,
        );
        $session = ['atlassian' => ['site_name' => 'Site', 'site_url' => 'https://site.test'], 'csrf_token' => 'csrf'];

        $response = $controller->show(new Request('GET', '/', ['year' => '2024', 'month' => '2', 'saved' => '1'], [], [], $session));

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertSame('rendered report', $response->body);
    }

    public function testRendersReportHtmlAndKeepsFormContext(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"accountId":"me","displayName":"User"}'),
            new MockResponse('{"issues":[]}'),
        ]);
        $translator = new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');
        $twig = new EnvironmentFactory()->create(dirname(__DIR__, 4) . '/templates', $translator);
        $jira = new JiraReportClient('https://site.test', new SymfonyJsonHttpTransport('https://api.test', $http), $translator);
        $timezone = ReportTimeZone::fromName('Europe/Warsaw');
        $generate = new GenerateMonthlyReportHandler(
            new JiraWorklogReportSource($jira, $timezone),
            new WorkdayPolicy(28800, new HolidayCalendarFactory()->create(HolidayCalendarFactory::COUNTRY_POLAND), new FixedClock(new \DateTimeImmutable('2024-03-01')), $timezone),
            28800,
        );
        $controller = new ReportPageController(
            $twig,
            static fn(array &$session): GenerateMonthlyReportHandler => $generate,
            static fn(array &$session): ReportViewFactory => new ReportViewFactory($translator, $jira->issueUrl(...)),
            new WorklogTagProvider(),
            true,
            $timezone,
        );
        $session = [
            'atlassian' => ['site_name' => 'Example Jira', 'site_url' => 'https://site.test'],
            'csrf_token' => 'csrf-value',
        ];

        $html = $controller->show(new Request(
            'GET',
            '/',
            ['year' => '2024', 'month' => '2', 'saved' => '1'],
            [],
            [],
            $session,
        ))->body;

        self::assertStringContainsString('href="https://site.test"', $html);
        self::assertStringContainsString('value="csrf-value"', $html);
        self::assertStringContainsString('formaction="/export.csv"', $html);
        self::assertStringContainsString('name="month"', $html);
        self::assertStringContainsString('aria-label="Poprzedni miesiąc"', $html);
        self::assertStringContainsString('aria-label="Następny miesiąc"', $html);
        self::assertStringContainsString('month=1&amp;year=2024', $html);
        self::assertStringContainsString('month=3&amp;year=2024', $html);
        self::assertStringContainsString('>Bieżący</a>', $html);
        self::assertStringContainsString('id="worklog-form" method="post" action="/worklogs"', $html);
        self::assertStringNotContainsString('name="action"', $html);
        self::assertStringContainsString('id="user-search-form"', $html);
        self::assertStringContainsString('id="issue-search-form"', $html);
        self::assertStringContainsString('id="worklog-dialog"', $html);
        self::assertStringContainsString('data-default-worklog-date="2024-02-01"', $html);
        self::assertStringContainsString('data-delete-confirm=', $html);
        self::assertStringContainsString('Pokaż tylko zadania z wpisami w dniu 2024-02-01', $html);
        self::assertStringContainsString('<script src="/js/report.js" defer></script>', $html);
        self::assertStringNotContainsString('const defaultWorklogDate', $html);
    }
}
