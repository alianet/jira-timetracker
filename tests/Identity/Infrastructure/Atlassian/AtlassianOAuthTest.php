<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Infrastructure\Atlassian;

use App\Identity\Application\Authentication\AccessTokenStore;
use App\Identity\Application\Authentication\AuthenticatedAccess;
use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Infrastructure\Atlassian\AtlassianOAuth;
use App\Kernel\Config\Config;
use App\Kernel\Exception\ApplicationConfigException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AtlassianOAuthTest extends TestCase
{
    public function testExchangesCodeAndMapsMatchingSiteWithoutExposingProviderPayload(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'access-value', 'refresh_token' => 'refresh-value', 'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                ['id' => 'cloud-id', 'url' => 'https://jira.example/', 'name' => 'Example Jira'],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $store = new MemoryTokenStore();
        $oauth = new AtlassianOAuth(
            'client-id',
            'client-secret',
            'https://app.example/oauth/callback',
            'https://jira.example',
            $http,
            $store,
        );

        $access = $oauth->exchange('valid-code');

        self::assertSame('cloud-id', $access->cloudId);
        self::assertSame('https://jira.example', $access->siteUrl);
        self::assertSame('Example Jira', $access->siteName);
        self::assertGreaterThan(time(), $access->expiresAt);
    }

    public function testConnectionExposesCredentialsAndApiBaseUrlWithoutTouchingTheNetwork(): void
    {
        $store = new MemoryTokenStore();
        $store->save(new AuthenticatedAccess('live-token', 'refresh', time() + 3600, 'cloud-id', 'https://jira.example', 'Example'));
        $http = new MockHttpClient([]);

        $connection = $this->oauth($http, $store)->connection();

        self::assertTrue($connection->authenticated);
        self::assertTrue($connection->isInteractive());
        self::assertSame(AuthenticationMode::InteractiveOAuth, $connection->mode);
        self::assertSame('live-token', $connection->token);
        self::assertSame('', $connection->login);
        self::assertSame('cloud-id', $connection->cloudId);
        self::assertSame('https://jira.example', $connection->siteUrl);
        self::assertSame('https://api.atlassian.com/ex/jira/cloud-id', $connection->apiBaseUrl);
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testConnectionRefreshesAnExpiredTokenAndPersistsIt(): void
    {
        $store = new MemoryTokenStore();
        $store->save(new AuthenticatedAccess('stale', 'refresh-value', time() - 10, 'cloud-id', 'https://jira.example', 'Example'));
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'fresh-token', 'refresh_token' => 'rotated-refresh', 'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $connection = $this->oauth($http, $store)->connection();

        self::assertSame('fresh-token', $connection->token);
        self::assertSame(1, $http->getRequestsCount());
        self::assertSame('fresh-token', $store->load()?->accessToken);
        self::assertSame('rotated-refresh', $store->load()?->refreshToken);
        self::assertSame('cloud-id', $store->load()?->cloudId);
    }

    public function testConnectionWithoutStoredTokensStaysUnauthenticatedInsteadOfFailing(): void
    {
        $http = new MockHttpClient([]);

        $connection = $this->oauth($http, new MemoryTokenStore())->connection();

        self::assertFalse($connection->authenticated);
        self::assertTrue($connection->isInteractive());
        self::assertSame('https://jira.example', $connection->siteUrl);
        self::assertSame('', $connection->token);
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testMissingOAuthConfigurationKeepsCurrentConfigurationError(): void
    {
        $directory = sys_get_temp_dir() . '/identity-config-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/.env', "ATLASSIAN_CLIENT_ID=id\n");
        $config = Config::fromDirectory($directory);

        $this->expectException(ApplicationConfigException::class);
        $this->expectExceptionMessage('Brak zmiennej ATLASSIAN_CLIENT_SECRET w pliku .env lub .env.local.');
        $config->required('ATLASSIAN_CLIENT_SECRET');
    }

    private function oauth(MockHttpClient $http, AccessTokenStore $store): AtlassianOAuth
    {
        return new AtlassianOAuth(
            'client-id',
            'client-secret',
            'https://app.example/oauth/callback',
            'https://jira.example',
            $http,
            $store,
        );
    }
}

final class MemoryTokenStore implements AccessTokenStore
{
    private ?AuthenticatedAccess $access = null;
    public function load(): ?AuthenticatedAccess
    {
        return $this->access;
    }
    public function save(AuthenticatedAccess $access): void
    {
        $this->access = $access;
    }
    public function clear(): void
    {
        $this->access = null;
    }
}
