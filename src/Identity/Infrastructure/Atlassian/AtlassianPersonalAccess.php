<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Atlassian;

use App\Identity\Application\Authentication\AuthenticatedAccess;
use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Application\Authentication\AuthorizationGateway;
use App\Identity\Application\Authentication\Connection;
use App\Identity\Application\Authentication\ConnectionProvider;

final readonly class AtlassianPersonalAccess implements AuthorizationGateway, ConnectionProvider
{
    public function __construct(
        private string $jiraUrl,
        private string $email,
        private string $apiToken,
    ) {}

    public function isInteractive(): bool
    {
        return false;
    }

    public function authorizationUrl(string $state): string
    {
        throw new \LogicException('Tryb individual nie używa logowania OAuth.');
    }

    public function exchange(string $code): AuthenticatedAccess
    {
        throw new \LogicException('Tryb individual nie używa logowania OAuth.');
    }

    public function connection(): Connection
    {
        if ($this->email === '' || $this->apiToken === '') {
            return Connection::unauthenticated(AuthenticationMode::PersonalToken, $this->jiraUrl);
        }

        return Connection::personalToken($this->jiraUrl, $this->email, $this->apiToken);
    }
}
