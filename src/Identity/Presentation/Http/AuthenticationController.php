<?php

// SPDX-FileCopyrightText: 2026 Mariusz Kaczanowski
// SPDX-License-Identifier: MIT

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Authentication\CompleteAuthorizationHandler;
use App\Identity\Application\Authentication\LogoutHandler;
use App\Identity\Application\Authentication\StartAuthorizationHandler;
use App\Kernel\Exception\ApplicationRuntimeException as WorkLogRuntimeException;
use App\Kernel\Presentation\Http\Request;
use App\Kernel\Presentation\Http\Response;
use App\Kernel\Support\ApiValue;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class AuthenticationController
{
    /**
     * @param \Closure(array<array-key, mixed>&): StartAuthorizationHandler $start
     * @param \Closure(array<array-key, mixed>&): CompleteAuthorizationHandler $complete
     * @param \Closure(array<array-key, mixed>&): LogoutHandler $logout
     */
    public function __construct(
        private \Closure $start,
        private \Closure $complete,
        private \Closure $logout,
        private SessionSecurity $security,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function login(Request $request): Response
    {
        $this->logger->info('User started Atlassian authentication.');
        $state = $this->security->randomToken();
        $url = ($this->start)($request->session)->handle($state);
        if ($url === null) {
            $this->logger->debug('OAuth login skipped for non-interactive authentication mode.');
            return Response::redirect('/', 302);
        }
        $request->session['oauth_state'] = $state;

        return Response::redirect($url, 302);
    }

    public function callback(Request $request): Response
    {
        $handler = ($this->complete)($request->session);
        if (!$handler->isInteractive()) {
            $this->logger->debug('OAuth callback skipped for non-interactive authentication mode.');
            return Response::redirect('/', 302);
        }
        $expectedState = ApiValue::stringValue($request->session['oauth_state'] ?? null);
        unset($request->session['oauth_state']);
        $handler->handle(
            $expectedState,
            ApiValue::stringValue($request->query['state'] ?? null),
            isset($request->query['error']) ? ApiValue::stringValue($request->query['error']) : null,
            ApiValue::stringValue($request->query['code'] ?? null),
        );
        $this->security->regenerateId();
        $request->session['csrf_token'] = $this->security->randomToken();
        $this->logger->info('User completed Atlassian authentication.');

        return Response::redirect('/');
    }

    public function logout(Request $request): Response
    {
        if (!hash_equals(
            ApiValue::stringValue($request->session['csrf_token'] ?? null),
            ApiValue::stringValue($request->post['csrf_token'] ?? null),
        )) {
            $this->logger->warning('Logout rejected because of invalid CSRF token.');
            throw WorkLogRuntimeException::create('Nieprawidłowy token wylogowania.');
        }
        ($this->logout)($request->session)->handle();
        $request->session = [];
        $this->security->regenerateId();
        $this->logger->info('User logged out.');

        return Response::redirect('/');
    }
}
