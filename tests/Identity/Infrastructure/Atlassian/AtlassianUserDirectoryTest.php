<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Infrastructure\Atlassian;

use App\Identity\Application\Query\UserSearchPhrase;
use App\Identity\Infrastructure\Atlassian\AtlassianTransport;
use App\Identity\Infrastructure\Atlassian\AtlassianUserDirectory;
use PHPUnit\Framework\TestCase;

final class AtlassianUserDirectoryTest extends TestCase
{
    public function testMapsAtlassianFieldsFiltersMissingIdsAndLimitsUsers(): void
    {
        $transport = new UserPickerTransport(['users' => [
            ['accountId' => '', 'displayName' => 'Skipped'],
            ['accountId' => 'a-1', 'displayName' => 'Anna', 'avatarUrls' => ['48x48' => 'https://avatar/1']],
            ['accountId' => 'a-2', 'displayName' => 'Jan', 'avatarUrl' => 'https://avatar/2'],
        ]]);
        $result = new AtlassianUserDirectory($transport)->search(UserSearchPhrase::fromString('an'), 1);

        self::assertCount(1, $result);
        self::assertSame('a-1', $result[0]->accountId->value);
        self::assertSame('Anna', $result[0]->displayName);
        self::assertSame('https://avatar/1', $result[0]->avatarUrl);
        self::assertSame([['GET', '/rest/api/3/user/picker', [
            'query' => 'an', 'maxResults' => 1, 'showAvatar' => 'true',
        ]]], $transport->requests);
    }
}

final class UserPickerTransport implements AtlassianTransport
{
    /** @var list<array{string, string, array<array-key, mixed>|null}> */
    public array $requests = [];

    /** @param array<string, mixed> $response */
    public function __construct(private array $response) {}

    public function request(string $method, string $path, ?array $query = null): array
    {
        $this->requests[] = [$method, $path, $query];

        return $this->response;
    }
}
