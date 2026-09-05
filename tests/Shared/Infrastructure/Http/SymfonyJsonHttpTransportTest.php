<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\HttpTransportException;
use App\Shared\Infrastructure\Http\SymfonyJsonHttpTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SymfonyJsonHttpTransportTest extends TestCase
{
    public function testBuildsRequestAndDecodesJson(): void
    {
        $request = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$request): MockResponse {
            $request = [$method, $url, $options];

            return new MockResponse('{"ok":true}');
        });

        $result = new SymfonyJsonHttpTransport('https://api.test/', $http)->request(
            'POST',
            '/resource',
            ['name' => 'entry'],
            ['expand' => 'fields'],
        );

        self::assertSame(['ok' => true], $result);
        self::assertSame('POST', $request[0]);
        self::assertSame('https://api.test/resource?expand=fields', $request[1]);
        self::assertJsonStringEqualsJsonString('{"name":"entry"}', $request[2]['body']);
    }

    public function testExposesStatusAndBodyWithoutKnowingProviderErrorSchema(): void
    {
        $transport = new SymfonyJsonHttpTransport(
            'https://api.test',
            new MockHttpClient(new MockResponse('{"providerError":"details"}', ['http_code' => 429])),
        );

        try {
            $transport->request('GET', '/resource');
            self::fail('Expected transport failure.');
        } catch (HttpTransportException $exception) {
            self::assertSame('unsuccessful_response', $exception->reason);
            self::assertSame(429, $exception->status);
            self::assertSame('{"providerError":"details"}', $exception->responseBody);
        }
    }
}
