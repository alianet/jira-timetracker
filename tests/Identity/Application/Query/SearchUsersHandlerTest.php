<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Application\Query;

use App\Identity\Application\Query\SearchUsersHandler;
use App\Identity\Application\Query\UserDirectory;
use App\Identity\Application\Query\UserSearchPhrase;
use PHPUnit\Framework\TestCase;

final class SearchUsersHandlerTest extends TestCase
{
    public function testNormalizesPhraseAndAppliesResultLimit(): void
    {
        $directory = new RecordingUserDirectory();
        new SearchUsersHandler($directory)->handle('  Jan  ');

        self::assertSame('Jan', $directory->phrase?->value);
        self::assertSame(10, $directory->limit);
    }

    public function testDoesNotCallDirectoryForEmptyOrTooShortPhrase(): void
    {
        $directory = new RecordingUserDirectory();
        $handler = new SearchUsersHandler($directory);

        self::assertSame([], $handler->handle(''));
        self::assertSame([], $handler->handle('x'));
        self::assertNull($directory->phrase);
    }
}

final class RecordingUserDirectory implements UserDirectory
{
    public ?UserSearchPhrase $phrase = null;
    public ?int $limit = null;

    public function search(UserSearchPhrase $phrase, int $limit): array
    {
        $this->phrase = $phrase;
        $this->limit = $limit;

        return [];
    }
}
