<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Presentation\Http;

use App\Identity\Application\Authentication\AccountType;
use App\Identity\Application\Authentication\AuthenticationMode;
use App\Identity\Application\Authentication\Connection;
use App\Identity\Presentation\Http\Authorization;
use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Presentation\Http\Dispatcher;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;
use App\Kernel\Presentation\Http\Route;
use App\Kernel\Presentation\Http\Router;
use App\Kernel\Session\Session;
use App\TimeTracking\Application\Handler\AddWorklogHandler;
use App\TimeTracking\Application\Handler\DeleteWorklogHandler;
use App\TimeTracking\Application\Handler\UpdateWorklogHandler;
use App\TimeTracking\Application\Port\WorklogGateway;
use App\TimeTracking\Domain\Model\IssueKey;
use App\TimeTracking\Domain\Model\NewWorklog;
use App\TimeTracking\Domain\Model\Worklog;
use App\TimeTracking\Domain\Model\WorklogId;
use App\TimeTracking\Infrastructure\Jira\JiraTimeFormat;
use App\TimeTracking\Presentation\Http\WorklogController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class HttpRoutingIntegrationTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function routes(): iterable
    {
        yield 'report' => ['GET', '/', 'report'];
        yield 'export' => ['GET', '/export.csv', 'report_export'];
        yield 'issues' => ['GET', '/api/issues/search', 'issues_search'];
        yield 'users' => ['GET', '/api/users/search', 'users_search'];
        yield 'login' => ['GET', '/login', 'login'];
        yield 'callback' => ['GET', '/oauth/callback', 'oauth_callback'];
        yield 'logout' => ['POST', '/logout', 'logout'];
        yield 'create' => ['POST', '/worklogs', 'worklog_create'];
        yield 'update' => ['POST', '/worklogs/42', 'worklog_update'];
        yield 'delete' => ['POST', '/worklogs/42/delete', 'worklog_delete'];
        yield 'legacy POST /' => ['POST', '/', 'worklog_legacy'];
    }

    #[DataProvider('routes')]
    public function testPublicRouteContract(string $method, string $path, string $expectedName): void
    {
        [$route] = $this->router()->match($method, $path);

        self::assertSame($expectedName, $route->name);
    }

    public function testHtmlFormDoesNotExposePutAndWrongMethodReturns405(): void
    {
        $this->expectException(MethodNotAllowedException::class);

        $this->router()->match('PUT', '/worklogs/42');
    }

    public function testCreateReturns303AfterPostAndCallsOnlyAddHandler(): void
    {
        $gateway = new RecordingWorklogGateway();
        $controller = $this->worklogs($gateway);
        $sessionData = ['csrf_token' => 'known'];
        $session = new Session($sessionData);
        $request = new Request('POST', '/worklogs', [], [
            'csrf_token' => 'known', 'issue' => ' app-7 ', 'date' => '2026-08-31',
            'time_spent' => '1h 30m', 'comment' => 'done',
        ], [], $session);

        $response = $controller->create($request);

        self::assertSame(303, $response->status);
        self::assertSame('/?year=2026&month=8&issue=APP-7&saved=1', $response->headers['Location']);
        self::assertCount(1, $gateway->added);
        self::assertSame([], $gateway->updated);
        self::assertSame([], $gateway->deleted);
    }

    public function testUpdateAndDeleteUsePathIdAndRedirectAfterPost(): void
    {
        $gateway = new RecordingWorklogGateway();
        $controller = $this->worklogs($gateway);
        $sessionData = ['csrf_token' => 'known'];
        $session = new Session($sessionData);
        $common = ['csrf_token' => 'known', 'issue' => 'APP-7', 'date' => '2026-08-31'];

        $updated = $controller->update(new Request('POST', '/worklogs/42', [], $common + [
            'time_spent' => '2h', 'comment' => 'changed',
        ], ['id' => '42'], $session));
        $deleted = $controller->delete(new Request('POST', '/worklogs/42/delete', [], $common, ['id' => '42'], $session));

        self::assertSame(303, $updated->status);
        self::assertSame(303, $deleted->status);
        self::assertSame('42', $gateway->updated[0]->id->toString());
        self::assertSame('42', $gateway->deleted[0][1]->toString());
    }

    public function testInvalidCsrfDoesNotCallAHandler(): void
    {
        $gateway = new RecordingWorklogGateway();
        $controller = $this->worklogs($gateway);
        $sessionData = ['csrf_token' => 'known'];
        $session = new Session($sessionData);

        try {
            $controller->create(new Request('POST', '/worklogs', [], ['csrf_token' => 'wrong'], [], $session));
            self::fail('Expected CSRF failure.');
        } catch (WorkLogRuntimeException $exception) {
            self::assertSame('Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.', $exception->getMessage());
        }

        self::assertSame([], $gateway->added);
    }

    public function testLegacyPostContractIsControlledAndRedirects(): void
    {
        $gateway = new RecordingWorklogGateway();
        $controller = $this->worklogs($gateway);
        $sessionData = ['csrf_token' => 'known'];
        $session = new Session($sessionData);
        $response = $controller->legacy(new Request('POST', '/', [], [
            'csrf_token' => 'known', 'issue' => 'APP-7', 'date' => '2026-08-31',
            'time_spent' => '1h', 'comment' => '', 'action' => 'save', 'worklog_id' => '',
        ], [], $session));

        self::assertSame(303, $response->status);
        self::assertCount(1, $gateway->added);
    }

    public function testResponseFactoriesPreserveStatusAndSecurityHeaders(): void
    {
        $json = Response::json(['issues' => []]);
        $html = Response::html('login', 401);

        self::assertSame(200, $json->status);
        self::assertSame('application/json; charset=UTF-8', $json->headers['Content-Type']);
        self::assertSame('no-store', $json->headers['Cache-Control']);
        self::assertSame('nosniff', $json->headers['X-Content-Type-Options']);
        self::assertSame(401, $html->status);
        self::assertSame('text/html; charset=UTF-8', $html->headers['Content-Type']);
    }

    public function testLoginRouteStaysPublicAndPrivateRoutesReturnSuitableLoginResponses(): void
    {
        $authorization = $this->authorization(AuthenticationMode::InteractiveOAuth, AccountType::Company);
        $sessionData = [];
        $session = new Session($sessionData);
        $dispatcher = new Dispatcher($this->router(), $authorization, $session);

        $publicResponse = $dispatcher->dispatch('GET', '/login', [], []);
        $privateResponse = $dispatcher->dispatch('GET', '/', [], []);
        $apiResponse = $dispatcher->dispatch('GET', '/api/issues/search', [], []);

        self::assertSame(200, $publicResponse->status);
        self::assertSame(401, $privateResponse->status);
        self::assertSame('login company', $privateResponse->body);
        self::assertSame(401, $apiResponse->status);
        self::assertSame('{"error":"authentication_required"}', $apiResponse->body);
        self::assertSame('application/json; charset=UTF-8', $apiResponse->headers['Content-Type']);
    }

    public function testIndividualModeKeepsMissingTokenError(): void
    {
        $authorization = $this->authorization(AuthenticationMode::PersonalToken, AccountType::Individual);
        $sessionData = [];
        $session = new Session($sessionData);
        $dispatcher = new Dispatcher($this->router(), $authorization, $session);

        $this->expectException(WorkLogRuntimeException::class);
        $this->expectExceptionMessage('Tryb individual wymaga skonfigurowanego tokenu API Atlassian.');

        $dispatcher->dispatch('GET', '/', [], []);
    }

    private function router(): Router
    {
        $response = static fn(Request $request): Response => new Response();

        return new Router([
            new Route('login', '/login', ['GET'], $response, true),
            new Route('oauth_callback', '/oauth/callback', ['GET'], $response, true),
            new Route('logout', '/logout', ['POST'], $response, true),
            new Route('issues_search', '/api/issues/search', ['GET'], $response),
            new Route('users_search', '/api/users/search', ['GET'], $response),
            new Route('report_export', '/export.csv', ['GET'], $response),
            new Route('worklog_create', '/worklogs', ['POST'], $response),
            new Route('worklog_update', '/worklogs/{id}', ['POST'], $response, requirements: ['id' => '\\d+']),
            new Route('worklog_delete', '/worklogs/{id}/delete', ['POST'], $response, requirements: ['id' => '\\d+']),
            new Route('worklog_legacy', '/', ['POST'], $response),
            new Route('report', '/', ['GET'], $response),
        ]);
    }

    private function authorization(AuthenticationMode $mode, AccountType $accountType): Authorization
    {
        return new Authorization(
            static fn(): Connection => Connection::unauthenticated($mode, 'https://jira.example'),
            new Environment(new ArrayLoader(['auth/login.html.twig' => 'login {{ accountType }}'])),
            $accountType,
        );
    }

    private function worklogs(WorklogGateway $gateway): WorklogController
    {
        return new WorklogController(
            static fn(): AddWorklogHandler => new AddWorklogHandler($gateway, JiraTimeFormat::units()),
            static fn(): UpdateWorklogHandler => new UpdateWorklogHandler($gateway, JiraTimeFormat::units()),
            static fn(): DeleteWorklogHandler => new DeleteWorklogHandler($gateway),
        );
    }
}

final class RecordingWorklogGateway implements WorklogGateway
{
    /** @var list<NewWorklog> */
    public array $added = [];
    /** @var list<Worklog> */
    public array $updated = [];
    /** @var list<array{IssueKey, WorklogId}> */
    public array $deleted = [];

    public function add(NewWorklog $worklog): void
    {
        $this->added[] = $worklog;
    }
    public function update(Worklog $worklog): void
    {
        $this->updated[] = $worklog;
    }
    public function delete(IssueKey $issue, WorklogId $id): void
    {
        $this->deleted[] = [$issue, $id];
    }
}
