<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\TimeTracking\Presentation\Http;

use App\TimeTracking\Application\Query\IssueDirectory;
use App\TimeTracking\Application\Query\IssueSearchPhrase;
use App\TimeTracking\Application\Query\IssueSearchResult;
use App\TimeTracking\Application\Query\SearchIssuesHandler;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Presentation\Http\SearchIssuesController;
use PHPUnit\Framework\TestCase;

final class SearchIssuesControllerTest extends TestCase
{
    public function testMapsQueryAndReturnsPublicJsonShapeWithSecurityHeaders(): void
    {
        $directory = new ControllerIssueDirectory();
        $response = new SearchIssuesController(new SearchIssuesHandler($directory))->search(['q' => ' APP ']);

        self::assertSame(['issues' => [[
            'key' => 'APP-7',
            'summary' => 'Summary',
            'url' => 'https://site.test/browse/APP-7',
        ]]], $response->data);
        self::assertSame('APP', $directory->phrase);
        self::assertSame('application/json; charset=UTF-8', $response->headers['Content-Type']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
    }

    public function testReturnsEmptyPublicCollectionForMissingPhrase(): void
    {
        $response = new SearchIssuesController(new SearchIssuesHandler(new ControllerIssueDirectory()))->search([]);

        self::assertSame(['issues' => []], $response->data);
    }
}

final class ControllerIssueDirectory implements IssueDirectory
{
    public ?string $phrase = null;

    public function search(IssueSearchPhrase $phrase, int $limit): array
    {
        $this->phrase = $phrase->value;

        return [new IssueSearchResult(
            IssueKey::fromString('APP-7'),
            'Summary',
            'https://site.test/browse/APP-7',
        )];
    }
}
