<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace Tests\Identity\Presentation\Http;

use App\Identity\Application\Authentication\AuthenticatedAccess;
use App\Identity\Application\Authentication\AuthorizationGateway;
use App\Identity\Application\Authentication\CompleteAuthorizationHandler;
use App\Identity\Application\Authentication\LogoutHandler;
use App\Identity\Application\Authentication\StartAuthorizationHandler;
use App\Identity\Infrastructure\Session\SessionAccessTokenStore;
use App\Identity\Infrastructure\Session\SessionTokenCipher;
use App\Identity\Presentation\Http\AuthenticationController;
use App\Identity\Presentation\Http\SessionSecurity;
use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Session\Session;
use PHPUnit\Framework\TestCase;

final class AuthenticationControllerTest extends TestCase
{
    public function testInteractiveLoginAndSuccessfulCallbackPreserveRedirectsAndSecureSession(): void
    {
        $gateway = new StubAuthorizationGateway(true);
        $security = new StubSessionSecurity();
        $sessionData = [];
        $session = new Session($sessionData);
        $controller = $this->controller($gateway, $security, $session);

        $login = $controller->login(new Request('GET', '/login', [], [], [], $session));
        self::assertSame(302, $login->status);
        self::assertSame('https://auth.example/?state=state-token', $login->headers['Location']);

        $callback = $controller->callback(new Request('GET', '/oauth/callback', [
            'state' => 'state-token', 'code' => 'authorization-code',
        ], [], [], $session));

        self::assertSame(303, $callback->status);
        self::assertSame('/', $callback->headers['Location']);
        self::assertArrayNotHasKey('oauth_state', $sessionData);
        self::assertSame('csrf-token', $sessionData['csrf_token']);
        self::assertArrayHasKey('atlassian', $sessionData);
        self::assertSame(1, $security->regenerations);
    }

    public function testCallbackRefusalAndMissingCodeKeepExistingMessagesAndDoNotStoreAccess(): void
    {
        foreach ([
            [['state' => 'state-token', 'error' => 'access_denied'], 'Logowanie Atlassian zostało anulowane lub odrzucone.'],
            [['state' => 'state-token'], 'Atlassian nie zwrócił kodu autoryzacyjnego.'],
        ] as [$query, $message]) {
            $sessionData = ['oauth_state' => 'state-token'];
            $session = new Session($sessionData);
            try {
                $this->controller(new StubAuthorizationGateway(true), new StubSessionSecurity(), $session)
                    ->callback(new Request('GET', '/oauth/callback', $query, [], [], $session));
                self::fail('Expected callback error.');
            } catch (WorkLogRuntimeException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
            self::assertArrayNotHasKey('atlassian', $sessionData);
            self::assertArrayNotHasKey('oauth_state', $sessionData);
        }
    }

    public function testIndividualModeSkipsOAuthAndKeepsRedirectContract(): void
    {
        $sessionData = [];
        $session = new Session($sessionData);
        $controller = $this->controller(new StubAuthorizationGateway(false), new StubSessionSecurity(), $session);
        self::assertSame(302, $controller->login(new Request('GET', '/login', [], [], [], $session))->status);
        self::assertSame(302, $controller->callback(new Request('GET', '/oauth/callback', [], [], [], $session))->status);
    }

    public function testLogoutRejectsInvalidCsrfAndClearsWholeSessionOnSuccess(): void
    {
        $security = new StubSessionSecurity();
        $sessionData = ['csrf_token' => 'known', 'atlassian' => ['access_token' => 'secret'], 'locale' => 'pl'];
        $session = new Session($sessionData);
        $controller = $this->controller(new StubAuthorizationGateway(true), $security, $session);

        try {
            $controller->logout(new Request('POST', '/logout', [], ['csrf_token' => 'wrong'], [], $session));
            self::fail('Expected CSRF error.');
        } catch (WorkLogRuntimeException $exception) {
            self::assertSame('Nieprawidłowy token wylogowania.', $exception->getMessage());
        }
        self::assertNotSame([], $sessionData);

        $response = $controller->logout(new Request('POST', '/logout', [], ['csrf_token' => 'known'], [], $session));
        self::assertSame(303, $response->status);
        self::assertSame([], $sessionData);
        self::assertSame(1, $security->regenerations);
    }

    private function controller(AuthorizationGateway $gateway, SessionSecurity $security, Session $session): AuthenticationController
    {
        return new AuthenticationController(
            static fn(): StartAuthorizationHandler => new StartAuthorizationHandler($gateway),
            fn(): CompleteAuthorizationHandler => new CompleteAuthorizationHandler(
                $gateway,
                $this->tokenStore($session),
            ),
            fn(): LogoutHandler => new LogoutHandler($this->tokenStore($session)),
            $security,
        );
    }

    private function tokenStore(Session $session): SessionAccessTokenStore
    {
        return new SessionAccessTokenStore(
            $session,
            new SessionTokenCipher('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),
        );
    }
}

final class StubAuthorizationGateway implements AuthorizationGateway
{
    public function __construct(private bool $interactive) {}
    public function isInteractive(): bool
    {
        return $this->interactive;
    }
    public function authorizationUrl(string $state): string
    {
        return 'https://auth.example/?state=' . $state;
    }
    public function exchange(string $code): AuthenticatedAccess
    {
        return new AuthenticatedAccess('access', 'refresh', 123, 'cloud', 'https://jira.example', 'Jira');
    }
}

final class StubSessionSecurity implements SessionSecurity
{
    public int $regenerations = 0;
    private int $tokens = 0;
    public function randomToken(): string
    {
        return ++$this->tokens === 1 ? 'state-token' : 'csrf-token';
    }
    public function regenerateId(): void
    {
        ++$this->regenerations;
    }
}
