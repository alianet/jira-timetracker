<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Application\Query;

use App\TimeTracking\Application\Query\IssueDirectory;
use App\TimeTracking\Application\Query\IssueSearchPhrase;
use App\TimeTracking\Application\Query\SearchIssuesHandler;
use PHPUnit\Framework\TestCase;

final class SearchIssuesHandlerTest extends TestCase
{
    public function testNormalizesPhraseAndAppliesResultLimit(): void
    {
        $directory = new RecordingIssueDirectory();
        new SearchIssuesHandler($directory)->handle('  żółć  ');

        self::assertSame('żółć', $directory->phrase?->value);
        self::assertSame(10, $directory->limit);
    }

    public function testDoesNotCallDirectoryForEmptyOrTooShortPhrase(): void
    {
        $directory = new RecordingIssueDirectory();
        $handler = new SearchIssuesHandler($directory);

        self::assertSame([], $handler->handle(' '));
        self::assertSame([], $handler->handle('a'));
        self::assertNull($directory->phrase);
    }
}

final class RecordingIssueDirectory implements IssueDirectory
{
    public ?IssueSearchPhrase $phrase = null;
    public ?int $limit = null;

    public function search(IssueSearchPhrase $phrase, int $limit): array
    {
        $this->phrase = $phrase;
        $this->limit = $limit;

        return [];
    }
}
