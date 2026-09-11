<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Authentication\AccountType;
use App\Identity\Application\Authentication\Connection;
use App\Kernel\Exception\ApplicationRuntimeException;
use App\Kernel\Presentation\Http\Response;
use Twig\Environment;

final readonly class Authorization
{
    public function __construct(
        /** @var \Closure(array<array-key, mixed>): Connection */
        private \Closure $connection,
        private Environment $twig,
        private AccountType $accountType,
    ) {}

    /**
     * The session is passed by reference: resolving a connection can refresh and persist rotated OAuth tokens.
     *
     * @param array<array-key, mixed> $session
     */
    public function requireAuthenticated(array &$session): ?Response
    {
        $connection = ($this->connection)($session);
        if ($connection->authenticated) {
            return null;
        }
        if (!$connection->isInteractive()) {
            throw ApplicationRuntimeException::create('Tryb individual wymaga skonfigurowanego tokenu API Atlassian.');
        }

        return Response::html($this->twig->render('auth/login.html.twig', [
            'accountType' => $this->accountType->value,
        ]), 401);
    }
}
