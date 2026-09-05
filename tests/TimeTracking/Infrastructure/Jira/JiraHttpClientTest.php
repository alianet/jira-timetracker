<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Infrastructure\Jira;

use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use App\Shared\Infrastructure\Http\SymfonyJsonHttpTransport;
use App\TimeTracking\Infrastructure\Jira\JiraHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JiraHttpClientTest extends TestCase
{
    public function testPassesQueryParametersToHttpTransport(): void
    {
        $capturedOptions = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;

            return new MockResponse('{"sections":[]}');
        });
        $client = new JiraHttpClient(new SymfonyJsonHttpTransport('https://api.test', $http), $this->translator());

        self::assertSame(['sections' => []], $client->request('GET', '/picker', null, ['query' => 'APP']));
        self::assertSame(['query' => 'APP'], $capturedOptions['query']);
    }

    /** @return iterable<string, array{int, string, string, string}> */
    public static function jiraErrors(): iterable
    {
        yield 'details' => [400, '{"errorMessages":["Brak uprawnień."],"errors":{"timeSpent":"Podaj czas."}}', 'Jira odrzuciła przesłane dane. Brak uprawnień. timeSpent: Podaj czas.', 'pl'];
        yield 'invalid body' => [500, '<html>Error</html>', 'Jira nie mogła wykonać operacji (HTTP 500).', 'pl'];
        yield 'permissions' => [403, '', 'Jira odmówiła wykonania tej operacji z powodu braku uprawnień.', 'pl'];
        yield 'English' => [400, '', 'Jira rejected the submitted data.', 'en'];
    }

    #[DataProvider('jiraErrors')]
    public function testPreservesApplicationErrorMapping(int $status, string $body, string $message, string $locale): void
    {
        $translator = new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');
        $translator->setLocale($locale);
        $client = new JiraHttpClient(new SymfonyJsonHttpTransport('https://api.test', new MockHttpClient(new MockResponse($body, ['http_code' => $status]))), $translator);

        $this->expectException(WorkLogRuntimeException::class);
        $this->expectExceptionMessage($message);
        $client->request('POST', '/worklog', []);
    }

    public function testMapsTransportFailure(): void
    {
        $translator = new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');
        $client = new JiraHttpClient(new SymfonyJsonHttpTransport('https://api.test', new MockHttpClient(static fn(): never => throw new \RuntimeException('timeout'))), $translator);

        $this->expectException(WorkLogRuntimeException::class);
        $this->expectExceptionMessage('Nie udało się połączyć z Jirą: timeout');
        $client->request('DELETE', '/worklog');
    }

    private function translator(): \Symfony\Contracts\Translation\TranslatorInterface
    {
        return new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');
    }
}
