<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Query\SearchUsersHandler;
use App\Kernel\Presentation\Http\JsonResponse;
use App\Kernel\Support\ApiValue;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class SearchUsersController
{
    public function __construct(
        private SearchUsersHandler $handler,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /** @param array<string, mixed> $query */
    public function search(array $query): JsonResponse
    {
        $phrase = ApiValue::stringValue($query['q'] ?? null);
        $users = array_map(static fn($user): array => [
            'accountId' => $user->accountId->value,
            'displayName' => $user->displayName,
            'avatarUrl' => $user->avatarUrl,
        ], $this->handler->handle($phrase));
        $this->logger->debug('Jira user search completed.', [
            'phrase_length' => strlen(trim($phrase)),
            'result_count' => count($users),
        ]);

        return new JsonResponse(['users' => $users]);
    }
}
