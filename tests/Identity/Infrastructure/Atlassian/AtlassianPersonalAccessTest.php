<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Infrastructure\Atlassian;

use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Infrastructure\Atlassian\AtlassianPersonalAccess;
use PHPUnit\Framework\TestCase;

final class AtlassianPersonalAccessTest extends TestCase
{
    public function testConnectionCarriesBasicAuthCredentialsAndTheSiteAsApiBaseUrl(): void
    {
        $connection = new AtlassianPersonalAccess('https://jira.example/', 'user@example.com', 'api-token')->connection();

        self::assertTrue($connection->authenticated);
        self::assertFalse($connection->isInteractive());
        self::assertSame(AuthenticationMode::PersonalToken, $connection->mode);
        self::assertSame('user@example.com', $connection->login);
        self::assertSame('api-token', $connection->token);
        self::assertSame('', $connection->cloudId);
        self::assertSame('https://jira.example', $connection->siteUrl);
        self::assertSame('https://jira.example', $connection->apiBaseUrl);
    }

    public function testMissingTokenYieldsAnUnauthenticatedNonInteractiveConnection(): void
    {
        $connection = new AtlassianPersonalAccess('https://jira.example', 'user@example.com', '')->connection();

        self::assertFalse($connection->authenticated);
        self::assertFalse($connection->isInteractive());
        self::assertSame('', $connection->token);
        self::assertSame('https://jira.example', $connection->siteUrl);
    }

    public function testOAuthEntryPointsStayUnavailableInIndividualMode(): void
    {
        $access = new AtlassianPersonalAccess('https://jira.example', 'user@example.com', 'api-token');

        $this->expectException(\LogicException::class);
        $access->authorizationUrl('state');
    }
}
