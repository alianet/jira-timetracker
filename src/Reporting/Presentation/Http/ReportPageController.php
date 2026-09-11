<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Reporting\Presentation\Http;

use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;
use App\Kernel\Support\ApiValue;
use App\Reporting\Application\GenerateMonthlyReportHandler;
use App\Reporting\Domain\ReportPeriod;
use App\Reporting\Domain\ReportTimeZone;
use App\Reporting\Domain\WorklogAuthorId;
use App\Reporting\Presentation\ViewModel\ReportPageContext;
use App\Reporting\Presentation\ViewModel\ReportViewContext;
use App\Reporting\Presentation\ViewModel\ReportViewFactory;
use App\Reporting\Presentation\ViewModel\WorklogTagProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Safe\Exceptions\DatetimeException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final readonly class ReportPageController
{
    /**
     * @param callable(): GenerateMonthlyReportHandler $generateReport
     * @param callable(): ReportViewFactory $viewFactory
     */
    public function __construct(
        private Environment $twig,
        private mixed $generateReport,
        private mixed $viewFactory,
        private WorklogTagProvider $tagProvider,
        private bool $exportEnabled,
        private ReportTimeZone $timezone,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @throws \JsonException
     * @throws DatetimeException
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    public function show(Request $request): Response
    {
        $query = ApiValue::stringMap($request->query);
        $reportQuery = ReportQuery::fromQuery($query);
        $authorId = trim($query['accountId'] ?? '') === '' ? null : WorklogAuthorId::fromString($query['accountId']);
        $generated = ($this->generateReport)()->handle(
            new ReportPeriod($reportQuery->year, $reportQuery->month, $this->timezone),
            $authorId,
        );
        $this->logger->info('Monthly Jira report generated.', [
            'year' => $reportQuery->year,
            'month' => $reportQuery->month,
            'selected_author' => $authorId !== null,
            'worklog_count' => count($generated->report->entries),
        ]);
        $site = ApiValue::object($request->session->get('atlassian'));
        $pageContext = new ReportPageContext(
            siteName: ApiValue::stringValue($site['site_name'] ?? null),
            siteUrl: ApiValue::stringValue($site['site_url'] ?? null),
            csrfToken: ApiValue::stringValue($request->session->get('csrf_token')),
            exportEnabled: $this->exportEnabled,
        );
        $view = ($this->viewFactory)()->create($generated->report, new ReportViewContext(
            accountId: $generated->accountId,
            displayName: $generated->displayName,
            avatarUrl: $generated->avatarUrl,
            isOwnReport: $generated->isOwnReport,
            defaultWorklogDate: $reportQuery->isCurrent() ? $reportQuery->now->format('Y-m-d') : $generated->report->period->startDate(),
            siteName: $pageContext->siteName,
            siteUrl: $pageContext->siteUrl,
            csrfToken: $pageContext->csrfToken,
            exportEnabled: $pageContext->exportEnabled,
            saved: ($query['saved'] ?? '') === '1',
            currentYear: (int) $reportQuery->now->format('Y'),
            currentMonth: (int) $reportQuery->now->format('n'),
            years: range((int) $reportQuery->now->format('Y') - 5, (int) $reportQuery->now->format('Y') + 1),
            worklogTags: $this->tagProvider->all(),
        ));

        return Response::html($this->twig->render('report/index.html.twig', ['report' => $view]));
    }
}
