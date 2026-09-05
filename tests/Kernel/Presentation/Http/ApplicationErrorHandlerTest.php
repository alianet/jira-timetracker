<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Kernel\Presentation\Http;

use App\Kernel\Presentation\Http\ApplicationErrorHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ApplicationErrorHandlerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function responses(): iterable
    {
        yield 'HTML' => ['/', '<section class="error-card">'];
        yield 'API' => ['/api/failure', '{"error":"Wystąpił nieoczekiwany błąd. Spróbuj ponownie później."}'];
    }

    #[DataProvider('responses')]
    public function testLogsExceptionAndReturnsSafeResponse(string $path, string $expectedBodyFragment): void
    {
        $exception = new \RuntimeException('Sensitive internal details');
        $logHandler = new TestHandler();
        $errorHandler = new ApplicationErrorHandler(new Logger('test', [$logHandler]));

        ob_start();
        $errorHandler->handle($exception, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]);
        $body = (string) ob_get_clean();

        self::assertSame(500, http_response_code());
        self::assertStringContainsString($expectedBodyFragment, $body);
        self::assertStringNotContainsString($exception->getMessage(), $body);
        self::assertTrue($logHandler->hasCriticalThatContains('Fatal application error.'));
        self::assertSame($exception, $logHandler->getRecords()[0]->context['exception']);
    }

    public function testStillReturnsSafeResponseWhenLoggingFails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('critical')
            ->willThrowException(new \RuntimeException('Log is not writable'));
        $errorHandler = new ApplicationErrorHandler($logger);

        ob_start();
        $errorHandler->handle(new \RuntimeException('Sensitive internal details'), ['REQUEST_URI' => '/']);
        $body = (string) ob_get_clean();

        self::assertSame(500, http_response_code());
        self::assertStringContainsString('Wystąpił nieoczekiwany błąd.', $body);
        self::assertStringNotContainsString('Sensitive internal details', $body);
        self::assertStringNotContainsString('Log is not writable', $body);
    }

    public function testDiscardsPartialOutputBeforeRenderingErrorPage(): void
    {
        $errorHandler = new ApplicationErrorHandler(new Logger('test', [new TestHandler()]));

        ob_start();
        $errorHandler->register(['REQUEST_URI' => '/']);
        echo 'Partially rendered sensitive response';
        $errorHandler->handle(new \RuntimeException('Failure'), ['REQUEST_URI' => '/']);
        $body = (string) ob_get_clean();

        self::assertStringContainsString('Wystąpił nieoczekiwany błąd.', $body);
        self::assertStringNotContainsString('Partially rendered sensitive response', $body);
    }

    public function testUsesRequestedLocaleForEmergencyResponse(): void
    {
        $errorHandler = new ApplicationErrorHandler(new Logger('test', [new TestHandler()]));

        ob_start();
        $errorHandler->handle(new \RuntimeException('Failure'), [
            'REQUEST_URI' => '/api/failure?locale=fr',
            'HTTP_ACCEPT_LANGUAGE' => 'pl-PL',
        ]);
        $body = (string) ob_get_clean();

        self::assertSame('{"error":"Une erreur inattendue est survenue. Veuillez réessayer plus tard."}', $body);
    }
}
