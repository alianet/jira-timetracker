<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Presentation\Http;

use App\Kernel\Exception\ApplicationRuntimeException;
use App\Kernel\Exception\RateLimitExceededException;
use App\Kernel\Presentation\Http\Dispatcher;
use App\Kernel\Presentation\Http\FrontController;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\RequestAuthorization;
use App\Kernel\Presentation\Http\Response;
use App\Kernel\Presentation\Http\Route;
use App\Kernel\Presentation\Http\Router;
use App\Kernel\Session\Session;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class FrontControllerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function errorResponses(): iterable
    {
        yield 'HTML' => ['/failure', 'A safe error page'];
        yield 'API' => ['/api/failure', '{"error":"A safe error page"}'];
    }

    #[DataProvider('errorResponses')]
    public function testLogsUnhandledExceptionAndDoesNotExposeItToTheUser(string $path, string $expectedBody): void
    {
        $exception = new \RuntimeException('Sensitive internal details');
        $route = new Route(
            'failure',
            $path,
            ['GET'],
            static function (Request $request) use ($exception): Response {
                throw $exception;
            },
            true,
        );
        $sessionData = [];
        $dispatcher = new Dispatcher(new Router([$route]), new AllowAllAuthorization(), new Session($sessionData));
        $twig = new Environment(new ArrayLoader(['error.html.twig' => '{{ message }}']));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('A safe error page');
        $handler = new TestHandler();
        $controller = new FrontController($dispatcher, $twig, $translator, new Logger('test', [$handler]));
        $_GET = [];
        $_POST = [];

        ob_start();
        $controller->handle(['REQUEST_URI' => $path, 'REQUEST_METHOD' => 'GET']);
        $body = (string) ob_get_clean();

        self::assertSame(500, http_response_code());
        self::assertSame($expectedBody, $body);
        self::assertStringNotContainsString($exception->getMessage(), $body);
        self::assertTrue($handler->hasErrorThatContains('Unhandled exception during HTTP request.'));
        $errorRecords = array_values(array_filter(
            $handler->getRecords(),
            static fn($record): bool => $record->level === Level::Error,
        ));
        $record = $errorRecords[0];
        self::assertSame($exception, $record->context['exception']);
        self::assertSame('GET', $record->context['method']);
        self::assertSame($path, $record->context['path']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function applicationErrorResponses(): iterable
    {
        yield 'HTML' => ['/failure', 'Jira session expired'];
        yield 'API' => ['/api/failure', '{"error":"Jira session expired"}'];
    }

    #[DataProvider('applicationErrorResponses')]
    public function testExposesApplicationErrorMessageToTheUser(string $path, string $expectedBody): void
    {
        $exception = ApplicationRuntimeException::create('Jira session expired');
        $route = new Route(
            'failure',
            $path,
            ['GET'],
            static function (Request $request) use ($exception): Response {
                throw $exception;
            },
            true,
        );
        $sessionData = [];
        $dispatcher = new Dispatcher(new Router([$route]), new AllowAllAuthorization(), new Session($sessionData));
        $twig = new Environment(new ArrayLoader(['error.html.twig' => '{{ message }}']));
        $translator = $this->createStub(TranslatorInterface::class);
        $handler = new TestHandler();
        $controller = new FrontController($dispatcher, $twig, $translator, new Logger('test', [$handler]));
        $_GET = [];
        $_POST = [];

        ob_start();
        $controller->handle(['REQUEST_URI' => $path, 'REQUEST_METHOD' => 'GET']);
        $body = (string) ob_get_clean();

        self::assertSame(500, http_response_code());
        self::assertSame($expectedBody, $body);
        self::assertTrue($handler->hasErrorThatContains('Unhandled exception during HTTP request.'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function rateLimitResponses(): iterable
    {
        yield 'HTML' => ['/failure', 'Jira is busy. Try again in 42 seconds.'];
        yield 'API' => ['/api/failure', '{"error":"Jira is busy. Try again in 42 seconds."}'];
    }

    #[DataProvider('rateLimitResponses')]
    public function testReturnsRateLimitResponseWithRetryInformation(string $path, string $expectedBody): void
    {
        $exception = RateLimitExceededException::withRetryAfter('Jira is busy. Try again in 42 seconds.', 42);
        $route = new Route(
            'failure',
            $path,
            ['GET'],
            static function (Request $request) use ($exception): Response {
                throw $exception;
            },
            true,
        );
        $sessionData = [];
        $dispatcher = new Dispatcher(new Router([$route]), new AllowAllAuthorization(), new Session($sessionData));
        $twig = new Environment(new ArrayLoader(['error.html.twig' => '{{ message }}']));
        $translator = $this->createStub(TranslatorInterface::class);
        $handler = new TestHandler();
        $controller = new FrontController($dispatcher, $twig, $translator, new Logger('test', [$handler]));
        $_GET = [];
        $_POST = [];

        ob_start();
        $controller->handle(['REQUEST_URI' => $path, 'REQUEST_METHOD' => 'GET']);
        $body = (string) ob_get_clean();

        self::assertSame(429, http_response_code());
        self::assertSame($expectedBody, $body);
        self::assertTrue($handler->hasWarningThatContains('External API rate limit exceeded during HTTP request.'));
    }
}

final class AllowAllAuthorization implements RequestAuthorization
{
    public function requireAuthenticated(): ?Response
    {
        return null;
    }
}
