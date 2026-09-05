<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Presentation\Http;

use App\Kernel\Presentation\Http\LocalhostRequestChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalhostRequestCheckerTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>}> */
    public static function localhostRequests(): iterable
    {
        yield 'localhost' => [['HTTP_HOST' => 'localhost']];
        yield 'localhost with port' => [['HTTP_HOST' => 'localhost:81']];
        yield 'case-insensitive localhost' => [['HTTP_HOST' => 'LOCALHOST:81']];
        yield 'localhost with trailing dot' => [['HTTP_HOST' => 'localhost.:81']];
        yield 'IPv4 loopback' => [['HTTP_HOST' => '127.0.0.1:81']];
        yield 'IPv4 loopback range' => [['HTTP_HOST' => '127.10.20.30']];
        yield 'IPv6 loopback' => [['HTTP_HOST' => '[::1]:81']];
        yield 'server name fallback' => [['SERVER_NAME' => 'localhost']];
    }

    /** @param array<string, mixed> $server */
    #[DataProvider('localhostRequests')]
    public function testRecognizesLocalhost(array $server): void
    {
        self::assertTrue(new LocalhostRequestChecker()->isLocalhost($server));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function nonLocalRequests(): iterable
    {
        yield 'missing host' => [[]];
        yield 'domain' => [['HTTP_HOST' => 'timetracker.example.com']];
        yield 'LAN address' => [['HTTP_HOST' => '192.168.1.20:81']];
        yield 'unspecified IPv4 address' => [['HTTP_HOST' => '0.0.0.0:81']];
        yield 'invalid loopback address' => [['HTTP_HOST' => '127.999.0.1']];
        yield 'userinfo impersonating localhost' => [['HTTP_HOST' => 'example.com@localhost']];
        yield 'localhost with path' => [['HTTP_HOST' => 'localhost/admin']];
        yield 'localhost with invalid port' => [['HTTP_HOST' => 'localhost:0']];
    }

    /** @param array<string, mixed> $server */
    #[DataProvider('nonLocalRequests')]
    public function testRejectsNonLocalhost(array $server): void
    {
        self::assertFalse(new LocalhostRequestChecker()->isLocalhost($server));
    }
}
