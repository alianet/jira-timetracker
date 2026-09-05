<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Http;

use App\Kernel\Presentation\Http\JsonResponse;
use App\Kernel\Support\ApiValue;
use App\TimeTracking\Application\Query\SearchIssuesHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class SearchIssuesController
{
    public function __construct(
        private SearchIssuesHandler $handler,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /** @param array<string, mixed> $query */
    public function search(array $query): JsonResponse
    {
        $phrase = ApiValue::stringValue($query['q'] ?? null);
        $issues = array_map(static fn($issue): array => [
            'key' => $issue->key->toString(),
            'summary' => $issue->summary,
            'url' => $issue->url,
        ], $this->handler->handle($phrase));
        $this->logger->debug('Jira issue search completed.', [
            'phrase_length' => strlen(trim($phrase)),
            'result_count' => count($issues),
        ]);

        return new JsonResponse(['issues' => $issues]);
    }
}
