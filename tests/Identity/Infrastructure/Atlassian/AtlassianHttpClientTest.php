<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Infrastructure\Atlassian;

use App\Identity\Infrastructure\Atlassian\AtlassianHttpClient;
use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Infrastructure\Translation\TranslatorFactory;
use App\Shared\Infrastructure\Http\SymfonyJsonHttpTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AtlassianHttpClientTest extends TestCase
{
    public function testPreservesUserDirectoryErrorMapping(): void
    {
        $client = new AtlassianHttpClient(
            new SymfonyJsonHttpTransport('https://api.test', new MockHttpClient(new MockResponse(
                '{"errorMessages":["Brak dostępu do katalogu."]}',
                ['http_code' => 403],
            ))),
            $this->translator(),
        );

        $this->expectException(WorkLogRuntimeException::class);
        $this->expectExceptionMessage('Jira odmówiła wykonania tej operacji z powodu braku uprawnień. Brak dostępu do katalogu.');
        $client->request('GET', '/rest/api/3/user/picker');
    }

    public function testMapsTransportFailure(): void
    {
        $client = new AtlassianHttpClient(
            new SymfonyJsonHttpTransport('https://api.test', new MockHttpClient(static fn(): never => throw new \RuntimeException('timeout'))),
            $this->translator(),
        );

        $this->expectException(WorkLogRuntimeException::class);
        $this->expectExceptionMessage('Nie udało się połączyć z Jirą: timeout');
        $client->request('GET', '/rest/api/3/user/picker');
    }

    private function translator(): \Symfony\Contracts\Translation\TranslatorInterface
    {
        return new TranslatorFactory()->create(dirname(__DIR__, 4) . '/translations', ['pl', 'en'], 'pl');
    }
}
