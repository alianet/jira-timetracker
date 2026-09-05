<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Infrastructure\Atlassian;

use App\Identity\Application\Query\AccountId;
use App\Identity\Application\Query\UserDirectory;
use App\Identity\Application\Query\UserSearchPhrase;
use App\Identity\Application\Query\UserSearchResult;
use App\Kernel\Support\ApiValue;

final readonly class AtlassianUserDirectory implements UserDirectory
{
    public function __construct(private AtlassianTransport $transport) {}

    public function search(UserSearchPhrase $phrase, int $limit): array
    {
        $payload = $this->transport->request('GET', '/rest/api/3/user/picker', [
            'query' => $phrase->value,
            'maxResults' => $limit,
            'showAvatar' => 'true',
        ]);
        $users = is_array($payload['users'] ?? null) ? $payload['users'] : [];
        $result = [];

        foreach ($users as $user) {
            if (!is_array($user)) {
                continue;
            }
            $accountId = ApiValue::stringValue($user['accountId'] ?? null);
            if ($accountId === '') {
                continue;
            }
            $avatars = ApiValue::object($user['avatarUrls'] ?? null);
            $result[] = new UserSearchResult(
                AccountId::fromString($accountId),
                ApiValue::stringValue($user['displayName'] ?? null),
                ApiValue::stringValue($user['avatarUrl'] ?? $avatars['48x48'] ?? $avatars['32x32'] ?? null),
            );
        }

        return array_slice($result, 0, $limit);
    }
}
