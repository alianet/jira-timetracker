<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Atlassian;

use App\Identity\Application\Authentication\AccessTokenStore;
use App\Identity\Application\Authentication\AuthenticatedAccess;
use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Application\Authentication\AuthorizationGateway;
use App\Identity\Application\Authentication\Connection;
use App\Identity\Application\Authentication\ConnectionProvider;
use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Support\ApiValue;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Safe\Exceptions\JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function Safe\json_decode;
use function Safe\parse_url;

final readonly class AtlassianOAuth implements AuthorizationGateway, ConnectionProvider
{
    private const string SCOPES = 'read:jira-work write:jira-work read:jira-user offline_access';

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $jiraUrl,
        private HttpClientInterface $httpClient,
        private AccessTokenStore $tokenStore,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function isInteractive(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://auth.atlassian.com/authorize?' . http_build_query([
            'audience' => 'api.atlassian.com',
            'client_id' => $this->clientId,
            'scope' => self::SCOPES,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'response_type' => 'code',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @throws \JsonException */
    public function exchange(string $code): AuthenticatedAccess
    {
        $this->logger->debug('Exchanging Atlassian OAuth authorization code.');
        $tokens = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);
        $resource = $this->matchingResource($tokens['access_token']);
        $this->logger->info('Atlassian OAuth authorization code exchanged.', [
            'site_host' => parse_url($resource['url'], PHP_URL_HOST),
        ]);

        return new AuthenticatedAccess(
            $tokens['access_token'],
            $tokens['refresh_token'],
            time() + $tokens['expires_in'],
            $resource['id'],
            rtrim($resource['url'], '/'),
            $resource['name'],
        );
    }

    /** @throws \JsonException */
    public function connection(): Connection
    {
        if ($this->tokenStore->load() === null) {
            $this->logger->debug('Atlassian OAuth connection is not authenticated.');
            return Connection::unauthenticated(AuthenticationMode::InteractiveOAuth, $this->jiraUrl);
        }
        // Site details first: resolving them may rotate the stored tokens, so read the access token afterwards.
        $site = $this->siteDetails();

        return Connection::oauth(
            $this->accessToken(),
            $site['cloud_id'],
            $site['site_url'],
            'https://api.atlassian.com/ex/jira/' . rawurlencode($site['cloud_id']),
        );
    }

    /** @throws \JsonException */
    private function accessToken(): string
    {
        $access = $this->tokenStore->load();
        if ($access === null) {
            throw WorkLogRuntimeException::create('Sesja logowania wygasła. Zaloguj się ponownie.');
        }

        if ($access->expiresAt > time() + 60) {
            $this->logger->debug('Using active Atlassian OAuth access token.');
            return $access->accessToken;
        }
        $refreshToken = $access->refreshToken ?? '';
        if ($refreshToken === '') {
            $this->tokenStore->clear();
            $this->logger->warning('Atlassian OAuth session expired without a refresh token.');
            throw WorkLogRuntimeException::create('Sesja Atlassian wygasła. Zaloguj się ponownie.');
        }
        $this->logger->info('Refreshing Atlassian OAuth access token.');
        $tokens = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);
        $this->tokenStore->save(new AuthenticatedAccess(
            $tokens['access_token'],
            $tokens['refresh_token'] ?? $refreshToken,
            time() + $tokens['expires_in'],
            $access->cloudId,
            $access->siteUrl,
            $access->siteName,
        ));
        $this->logger->info('Atlassian OAuth access token refreshed.');

        return $tokens['access_token'];
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return array{access_token: string, refresh_token: string|null, expires_in: int}
     * @throws \JsonException
     * @throws JsonException
     */
    private function tokenRequest(array $payload): array
    {
        $grantType = is_string($payload['grant_type'] ?? null) ? $payload['grant_type'] : 'unknown';
        $startedAt = microtime(true);
        $this->logger->debug('Sending Atlassian OAuth token request.', ['grant_type' => $grantType]);

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://auth.atlassian.com/oauth/token',
                ['json' => $payload, 'timeout' => 30]
            );
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (Throwable $e) {
            $this->logger->error('Atlassian OAuth token request failed.', [
                'exception' => $e,
                'grant_type' => $grantType,
                'duration_ms' => self::duration($startedAt),
            ]);
            throw WorkLogRuntimeException::create('Nie udało się połączyć z Atlassian.', $e);
        }
        if ($status < 200 || $status >= 300) {
            $this->logger->error('Atlassian rejected OAuth token request.', [
                'grant_type' => $grantType,
                'status' => $status,
                'duration_ms' => self::duration($startedAt),
            ]);
            throw WorkLogRuntimeException::create("Atlassian odrzucił logowanie (HTTP {$status}).");
        }
        $this->logger->debug('Atlassian OAuth token request completed.', [
            'grant_type' => $grantType,
            'status' => $status,
            'duration_ms' => self::duration($startedAt),
        ]);
        $tokens = ApiValue::object(json_decode($content, true));
        $accessToken = $tokens['access_token'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            $this->logger->error('Atlassian OAuth response did not contain an access token.', [
                'grant_type' => $grantType,
            ]);
            throw WorkLogRuntimeException::create('Atlassian nie zwrócił tokenu dostępu.');
        }

        $refreshToken = $tokens['refresh_token'] ?? null;

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($refreshToken) ? $refreshToken : null,
            'expires_in' => ApiValue::intValue($tokens['expires_in'] ?? 3600),
        ];
    }

    /**
     * @return array{id: string, url: string, name: string}
     * @throws \JsonException
     * @throws JsonException
     */
    private function matchingResource(string $accessToken): array
    {
        $startedAt = microtime(true);
        $this->logger->debug('Fetching accessible Atlassian resources.');

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.atlassian.com/oauth/token/accessible-resources',
                [
                    'auth_bearer' => $accessToken,
                    'headers' => ['Accept' => 'application/json'],
                    'timeout' => 30,
                ]
            );
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (Throwable $exception) {
            $this->logger->error('Fetching accessible Atlassian resources failed.', [
                'exception' => $exception,
                'duration_ms' => self::duration($startedAt),
            ]);
            throw WorkLogRuntimeException::create('Nie udało się pobrać witryn Atlassian.', $exception);
        }
        if ($status < 200 || $status >= 300) {
            $this->logger->error('Atlassian rejected accessible resources request.', [
                'status' => $status,
                'duration_ms' => self::duration($startedAt),
            ]);
            throw WorkLogRuntimeException::create("Nie udało się pobrać witryn Atlassian (HTTP {$status}).");
        }
        $wantedUrl = rtrim(strtolower($this->jiraUrl), '/');
        $resources = json_decode($content, true);

        if (!is_array($resources)) {
            throw WorkLogRuntimeException::create('Atlassian zwrócił nieprawidłową listę witryn.');
        }

        foreach ($resources as $resourceValue) {
            $resource = ApiValue::object($resourceValue);
            $url = ApiValue::stringValue($resource['url'] ?? null);

            if ($url !== '' && rtrim(strtolower($url), '/') === $wantedUrl) {
                $this->logger->debug('Matched configured Jira site in Atlassian resources.', [
                    'site_host' => parse_url($url, PHP_URL_HOST),
                    'duration_ms' => self::duration($startedAt),
                ]);
                return [
                    'id' => ApiValue::stringValue($resource['id'] ?? null),
                    'url' => $url,
                    'name' => ApiValue::stringValue($resource['name'] ?? null) ?: $url,
                ];
            }
        }
        $this->logger->error('Configured Jira site is missing from accessible Atlassian resources.', [
            'site_host' => parse_url($this->jiraUrl, PHP_URL_HOST),
        ]);
        throw WorkLogRuntimeException::create("Konto nie ma dostępu do skonfigurowanej witryny Jira: {$this->jiraUrl}");
    }

    /**
     * @return array{cloud_id: string, site_url: string}
     */
    private function siteDetails(): array
    {
        $access = $this->tokenStore->load();
        if ($access === null) {
            throw WorkLogRuntimeException::create('Sesja logowania wygasła. Zaloguj się ponownie.');
        }
        $cloudId = $access->cloudId;
        $siteUrl = $access->siteUrl;

        if ($cloudId !== '' && $siteUrl !== '') {
            return ['cloud_id' => $cloudId, 'site_url' => $siteUrl];
        }

        $tokens = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $access->refreshToken ?? '',
        ]);
        $resource = $this->matchingResource($tokens['access_token']);
        $this->tokenStore->save(new AuthenticatedAccess(
            $tokens['access_token'],
            $tokens['refresh_token'] ?? $access->refreshToken,
            time() + $tokens['expires_in'],
            $resource['id'],
            rtrim($resource['url'], '/'),
            $resource['name'],
        ));

        return [
            'cloud_id' => $resource['id'],
            'site_url' => rtrim($resource['url'], '/'),
        ];
    }

    private static function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
