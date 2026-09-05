<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Presentation\Http;

use App\Identity\Application\Query\AccountId;
use App\Identity\Application\Query\SearchUsersHandler;
use App\Identity\Application\Query\UserDirectory;
use App\Identity\Application\Query\UserSearchPhrase;
use App\Identity\Application\Query\UserSearchResult;
use App\Identity\Presentation\Http\SearchUsersController;
use PHPUnit\Framework\TestCase;

final class SearchUsersControllerTest extends TestCase
{
    public function testMapsQueryAndReturnsPublicJsonShapeWithSecurityHeaders(): void
    {
        $directory = new ControllerUserDirectory();
        $response = new SearchUsersController(new SearchUsersHandler($directory))->search(['q' => ' Anna ']);

        self::assertSame(['users' => [[
            'accountId' => 'account-1',
            'displayName' => 'Anna',
            'avatarUrl' => 'https://avatar.test/anna',
        ]]], $response->data);
        self::assertSame('Anna', $directory->phrase);
        self::assertSame('application/json; charset=UTF-8', $response->headers['Content-Type']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
    }

    public function testReturnsEmptyPublicCollectionForMissingPhrase(): void
    {
        $response = new SearchUsersController(new SearchUsersHandler(new ControllerUserDirectory()))->search([]);

        self::assertSame(['users' => []], $response->data);
    }
}

final class ControllerUserDirectory implements UserDirectory
{
    public ?string $phrase = null;

    public function search(UserSearchPhrase $phrase, int $limit): array
    {
        $this->phrase = $phrase->value;

        return [new UserSearchResult(
            AccountId::fromString('account-1'),
            'Anna',
            'https://avatar.test/anna',
        )];
    }
}
